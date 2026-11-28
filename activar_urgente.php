<?php
// La licencia es administrada por Supabase en validar_licencia.php.
// Esta ruta histórica no debe permitir activar licencias ni modificar la base local.
http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
echo 'Esta ruta de activación local fue deshabilitada. Usa el flujo oficial de licencias.';
