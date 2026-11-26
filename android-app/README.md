# App Android sencilla para POS System Pro

Esta app es una envoltura Android ligera que abre el POS web instalado en el servidor XAMPP. Usa la aplicación web y sus sesiones; no copia PHP ni MySQL al teléfono. El inicio carga el sistema completo, así que los módulos disponibles dependerán del usuario y sus permisos.

## URL configurada

`http://192.168.1.50/POS-System-Pro/`

La app está configurada para permitir HTTP **solo** hacia `192.168.1.50`; el resto de destinos HTTP en claro se bloquea. Para cambiar el servidor, actualiza la URL y `SERVER_HOST` en `app/src/main/java/com/possystempro/mobile/MainActivity.kt`, y el host permitido en `app/src/main/res/xml/network_security_config.xml`.

## Requisitos para probar

- Windows con Android Studio instalado (incluye JDK/Gradle compatibles) o Android SDK y Gradle configurados.
- XAMPP iniciado con Apache y MySQL, y POS instalado en `htdocs/POS-System-Pro`.
- El equipo servidor debe tener la IP `192.168.1.50` en la red local; reserva esa IP en el router para que no cambie.
- Teléfono/tablet y PC conectados a la misma Wi-Fi.
- Permitir Apache en el Firewall de Windows para redes privadas. No hace falta exponer MySQL a la red: la app habla con Apache y PHP, y PHP conecta localmente con MySQL.

Comprueba primero desde el navegador del teléfono que abre `http://192.168.1.50/POS-System-Pro/` y que puedes iniciar sesión. Si no abre, revisa que Apache esté iniciado, que la IP sea correcta y que el Firewall permita las conexiones entrantes al puerto HTTP.

## Generar APK de prueba

1. Abre la carpeta `android-app` en Android Studio.
2. Acepta la sincronización de Gradle e instala Android SDK 35 si lo solicita.
3. Conecta un dispositivo Android con depuración USB activada o crea un emulador.
4. Ejecuta **Build > Build Bundle(s) / APK(s) > Build APK(s)**.
5. Android Studio mostrará la ubicación de la APK de depuración. Para distribuirla fuera del entorno de prueba, genera una APK firmada desde **Build > Generate Signed Bundle / APK**.

## Seguridad

Esta configuración HTTP es para una red local de confianza y pruebas. HTTP no cifra credenciales ni datos de ventas dentro de la Wi-Fi. Para uso real, configura HTTPS con un certificado válido y elimina la excepción de HTTP de `network_security_config.xml`. No publiques el servidor XAMPP directamente en Internet; protege el POS, cambia las credenciales predeterminadas y configura copias de seguridad.
