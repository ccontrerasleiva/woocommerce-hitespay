<?php

/*
 * Plugin Name: WooCommerce Hites Payment Gateway
 * Plugin URI: https://github.com/ccontrerasleiva/woocommerce-hitespay
 * Description: Permite pagos usando tarjeta Hites
 * Author: Cristian Contreras
 * Author URI: http://www.contreras.tk
 * Version: 1.0
 *
 */


require_once 'vendor/autoload.php';
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;

add_filter( 'woocommerce_payment_gateways', 'hites_add_gateway_class' );
function hites_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_Hites_Gateway'; 
	return $gateways;
}

add_filter('woocommerce_get_return_url','override_return_url',10,2);

function override_return_url($return_url,$order){

    $oid = array(
        'order_id' => $order->get_id()
    );
    $url_extension = http_build_query($oid);
    return $return_url.'&'.$url_extension;;

  }

add_action( 'plugins_loaded', 'hites_init_gateway_class' );
function hites_init_gateway_class() {
 
	class WC_Hites_Gateway extends WC_Payment_Gateway {


        const TESTING_AUTH_URL= 'https://api-proxy.test-hites.cl/hites/pay/autoriza';
        const PROD_AUTH_URL = 'https://api-hitespay.tarjetahites.com/hites/pay/autoriza';

        const TESTING_CONFIRMTRX_URL = 'https://api-proxy.test-hites.cl/hites/pay/confirmaTrx';
        const PROD_CONFIRMTRX_URL = 'https://api-hitespay.tarjetahites.com/hites/pay/confirmaTrx';
 
 		/**
 		 * Class constructor
 		 */
 		public function __construct() {
            $this->id = 'hites'; 
            $this->icon = plugin_dir_url( __FILE__ ).'hitespay_tarjeta-ok.jpg'; 
            $this->has_fields = false; 
            $this->method_title = 'HitesPay';
            $this->method_description = 'Configuración medio de pago Hites';
        
            $this->supports = array(
                'products'
            );
        
            $this->init_form_fields();
        
            $this->init_settings();
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );
            $this->testmode = 'yes' == $this->get_option( 'testmode' );
            $this->authUrl = $this->testmode ? self::TESTING_AUTH_URL : self::PROD_AUTH_URL;
            $this->confirmUrl = $this->testmode ? self::TESTING_CONFIRMTRX_URL : self::PROD_CONFIRMTRX_URL;
            $this->codComercio = $this->get_option( 'cod_comercio' );
            $this->codLocal = $this->get_option( 'cod_local' );
            $this->privateKey = $this->get_option( 'private_key' );
        
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
 		}
 

 		public function init_form_fields(){
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Activa/Desactiva',
                    'label'       => 'Activar HitesPay',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Título',
                    'type'        => 'text',
                    'description' => 'Controla el titulo del medio de pago en la pagina de checkout',
                    'default'     => 'Paga con Hites',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'Indica el descriptor del medio de pago en la pagina de checkout',
                    'default'     => 'Paga con tu tarjeta Hites de manera segura',
                ),
                'testmode' => array(
                    'title'       => 'Modo Testing',
                    'label'       => 'Activa modo testing',
                    'type'        => 'checkbox',
                    'description' => 'Indica si el portal de pagos esta en modo de prueba o productivo',
                    'default'     => 'yes',
                    'desc_tip'    => true,
                ),
                'cod_comercio' => array(
                    'title'       => 'Código Comercio',
                    'type'        => 'text',
                    'description' => 'Código Comercio entregado por Hites'
                ),
                'cod_local' => array(
                    'title'       => 'Código Local',
                    'type'        => 'text',
                    'description' => 'Código Local entregado por hites, si es solo la tienda, el valor es 1',
                    'default'     => '1'                    
                ),
                'private_key' => array(
                    'title'       => 'Llave Privada',
                    'type'        => 'textarea'
                )
            );
        
        
 
        }

        
		public function process_payment( $order_id ) {
            global $woocommerce;
            
            $order = new WC_Order( $order_id );
            
            $client = new \GuzzleHttp\Client();

            $objAuth = [
                'codComercio'   => $this->codComercio,
                'monto'         => number_format($order->get_total(), 0, '',''),
                'url'           => $this->get_return_url( $order ),
                'codLocal'      => $this->codLocal
             ] ;
            try {
                $r = $client->request('POST', $this->authUrl, ['json' => $objAuth]);
                if($r->getStatusCode()==200){
                    $resJson = json_decode($r->getBody()->getContents());
                    print_r($resJson);
                    if($resJson->code == 0){
                        $order->update_status('on-hold', 'Esperando confirmación de pago desde Hites');
                        $order->update_meta_data( '_token', $resJson->token );
                        $order->save();
                        return array(
                            'result' => 'success',
                            'redirect' => $resJson->urlBotonPago
                        );
                    }
                    else {
                        wc_add_notice($resJson->estado, 'error' );
                        return array(
                            'result' => 'failure',
                            'redirect' => ''
                        );
                    }
                    
                }
            }
            catch(RequestException $e){
                $e->hasResponse() ? wc_add_notice(Psr7\str($e->getResponse()), 'error' ) : wc_add_notice('Ha ocurrido un error inesperado, favor intente nuevamente mas tarde', 'error' );
                return array(
                    'result' => 'failure',
                    'redirect' => ''
                );
            }
            
 
        }
         
 	}
}

add_action( 'woocommerce_thankyou', 'check_response', 1 );
function check_response($order_id) {
    global $woocommerce;
    $order = new WC_Order( $order_id );
    if($order->get_payment_method() == "hites"){
        $hitesPay = new WC_Hites_Gateway();
        if( $order->has_status('completed') || $order->has_status('processing')) {
            return;
        }
        if( $order_id == 0 || $order_id == '' ) {
            return;
        }

        $client = new \GuzzleHttp\Client();
        try{
            $r = $client->request('POST', $hitesPay->confirmUrl, [
                'headers' => [
                    'Authorization' => 'Bearer '.get_post_meta( $order_id, '_token', true )
                ],
                'json' => '']
            );
            if($r->getStatusCode()==200){
                $body = $r->getBody()->getContents();
                $resJson = json_decode($body);
                if($resJson->code == 0){
                    $res = openssl_get_privatekey($hitesPay->privateKey);
                    openssl_private_decrypt(base64_decode($resJson->fecha),$fecha,$res);
                    openssl_private_decrypt(base64_decode($resJson->hora),$hora,$res);
                    openssl_private_decrypt(base64_decode($resJson->codAutorizacion),$codAuth,$res);
                    openssl_private_decrypt(base64_decode($resJson->cantidadCuotas),$cuotas,$res);
                    openssl_private_decrypt(base64_decode($resJson->message),$message,$res);
                    openssl_private_decrypt(base64_decode($resJson->montoTotal),$montoTotal,$res);


                    $order->update_meta_data( '_fechaOperacion', $fecha );
                    $order->update_meta_data( '_horaOperacion', $hora );
                    $order->update_meta_data( '_codAutorizacion', $codAuth );
                    $order->update_meta_data( '_cantidadCuotas', $cuotas );
                    $order->update_meta_data( '_montoTotal', $montoTotal );
                    
                    $order->update_meta_data( '_mensajeHites', $message );
                    $order->update_status( 'processing' );
                    $order->add_order_note( sprintf( __( 'Hites Pay - Pago aprobado. <br />ID Transacción: %s <br /> Fecha Operacion: %s <br /> Hora Operación: %s <br /> Cod. Autorización: %s <br /> Cuotas: %s <br /> Monto Total: %s<br /> Mensaje: %s', 'woocommerce' ), $order_id, $fecha, $hora, $codAuth, $cuotas, $montoTotal, $message ) );
                
                    $woocommerce->cart->empty_cart();
                }
                else{
                    openssl_private_decrypt(base64_decode($resJson->message),$message,$res);
                    $order->update_status('cancelled');
                    $order->add_order_note( sprintf( __( 'Hites Pay - Pago anulado por el usuario, Transaction ID: %s <br />Mensaje Hites: %s', 'woocommerce' ), $order_id, $message ) );
                    $woocommerce->cart->empty_cart();
                    wp_safe_redirect( $order->get_checkout_payment_url() );
                    exit;
                }
            }            
        }
        catch(RequestException $e){
            wc_add_notice(sprintf('Lo sentimos, La transacción %s no pudo llevarse a cabo: %s', $order_id, $e->hasResponse() ? Psr7\str($e->getResponse()) : ''), 'error' );
            $order->update_status('failed', sprintf( 'Hites Pay - Pago fallido, Transaction ID: %s', 'woocommerce', $order_id ) . ' ' . $e->hasResponse() ? Psr7\str($e->getResponse()) : '' );
            wp_redirect($order->get_checkout_payment_url());
        }    
        
    }
    return;
 }
