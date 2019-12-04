# Plugin de pago para Hites Pay

## Compatibilidad
El plugin esta probado en:

 - Wordpress 5.0 >
 - Woocommerce 3.0 >

## Algunos alcances técnicos

Para utilizar hitesPay se debe contar con un certificado asimétrico que debes proveer, ya que de esta manera se realiza el encriptado de la respuesta final (confirmaTrx).

Este certificado es Autogenerado y en formato DER, por lo cual debes generarlo con OpenSSL con los siguientes comandos:

Clave Privada (esta es la que usaras dentro del Plugin):

     openssl genpkey -algorithm RSA -out private_key.pem
  
Clave Pública

    openssl rsa -pubout -in private_key.pem -out public_key.pem

Finalmente, la clave pública debes transformarla a formato DER con el siguiente comando (esta es la que enviarás a Hites)

    openssl rsa -pubin -in public_key.pem -outform der -out public_key.der

Para efectos de prueba, el repositorio cuenta con la llave privada del ambiente de test de Hites en formato PEM.

## Instrucciones de Instalación

 1. Descargue desde el repositorio como archivo ZIP
 2. Ingrese a la sección Plugins de Wordpress
 3. Suba el archivo ZIP e Instale

## Configuración

En la sección **payments** de **Woocommerce**, busca el medio de pago *Hites Pay*
Llena los campos :

 - Título : Nombre que mostrara en la pagina de checkout para tu medio de pago, por defecto "**Paga con Hites**"
 - Descripción : Descriptor del medio de pago
 - Modo Testing: activa o desactiva las peticiones a la api de testing de Hites
 - Código Comercio: Es el codigo asociado a tu tienda, en testing es 930
 - Código Local : Es el código que asigna Hites a los locales de tu tienda, por lo general deberas usar el código de local 1
 - Llave Privada : es el contenido de la llave generada para establecer la comunicación con Hites en la sección de alcances técnicos (abrir private_key.pem, copiar el contenido y pegarlo en este campo).
