<?php
session_start();
include 'conexion.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('America/Caracas');

if (!isset($_SESSION['usuario'])) { 
    exit("Sesión expirada"); 
}

$usuario_activo = $_SESSION['usuario'];

// Función para truncar a 2 decimales sin redondear hacia arriba
function truncar2($numero) {
    return floor(((float)$numero) * 100) / 100;
}

function limpiarNumero($numero) {
    if (empty($numero)) return 0;
    $num = trim(str_replace(',', '.', $numero));
    return (float) $num;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if (isset($_POST['token_venta'])) {
        if (!isset($_SESSION['token_venta']) || $_POST['token_venta'] !== $_SESSION['token_venta']) {
            header("Location: ventas.php");
            exit();
        }
        unset($_SESSION['token_venta']);
    }

    $carrito = json_decode($_POST['items_carrito'], true) ?? [];
    $metodo_pago = trim(mysqli_real_escape_string($conexion, $_POST['metodo_pago']));
    $fecha_actual = date('Y-m-d H:i:s'); 
    
    $tasa = limpiarNumero($_POST['tasa']);
    if ($tasa <= 0) $tasa = 1;

    $avance_usd = limpiarNumero($_POST['avance_total_cobrado_usd'] ?? 0);

    // Inputs nativos
    $p_usd     = limpiarNumero($_POST['pago_efectivo_usd'] ?? 0);
    $p_bs      = limpiarNumero($_POST['pago_efectivo_bs'] ?? 0);
    $p_punto   = limpiarNumero($_POST['pago_punto_bs'] ?? 0);
    $p_movil   = limpiarNumero($_POST['pago_movil_bs'] ?? 0);
    $p_biopago = limpiarNumero($_POST['pago_biopago_bs'] ?? 0);

    $monto_financiado = limpiarNumero($_POST['monto_financiado'] ?? 0);
    $monto_inicial   = limpiarNumero($_POST['monto_inicial'] ?? 0);
    $monto_recargo   = limpiarNumero($_POST['monto_recargo'] ?? 0);

    $id_val          = !empty($_POST['id_cliente_credito']) ? mysqli_real_escape_string($conexion,$_POST['id_cliente_credito']) : "NULL";
    $nombre_cliente = !empty($_POST['cliente_nombre']) ? mysqli_real_escape_string($conexion,$_POST['cliente_nombre']) : 'Público General';
    
    if (empty($carrito) &&$avance_usd <= 0) { 
        header("Location: ventas.php"); 
        exit(); 
    }

    // 1. OBTENER CONFIGURACIÓN FISCAL GLOBAL (IVA / IGTF)
    $res_cfg_imp = mysqli_query($conexion, "SELECT usar_iva, impuesto_iva, usar_igtf, impuesto_igtf FROM configuracion WHERE id = 1");
    $cfg_imp = mysqli_fetch_assoc($res_cfg_imp);
    $usar_iva_global = (int)($cfg_imp['usar_iva'] ?? 0);
    $porcentaje_iva = (float)($cfg_imp['impuesto_iva'] ?? 16.00);

    // 2. CALCULAR TOTALES Y BASE IMPONIBLE LÍNEA POR LÍNEA
    $total_productos_usd = 0;
    $total_productos_bs  = 0; 
    $base_imponible_iva_usd = 0;

    foreach ($carrito as $item) {$p_original = limpiarNumero($item['precio']);$desc_porc  = limpiarNumero($item['descuento'] ?? 0);$cant_item  = limpiarNumero($item['cantidad']);$usa_fiscal = isset($item['usa_fiscal']) ? (int)$item['usa_fiscal'] : 1;
        
        $precio_con_desc     =$p_original * (1 - ($desc_porc / 100));$subtotal_item_usd   = ($precio_con_desc * $cant_item);
        
        $total_productos_usd +=$subtotal_item_usd; 
        
        // Acumular base imponible si el producto usa parámetros fiscales/IVA
        if ($usar_iva_global === 1 &&$usa_fiscal === 1) {
            $base_imponible_iva_usd +=$subtotal_item_usd;
        }
        
        $precio_bs_item       = $precio_con_desc * $tasa;
        $total_productos_bs  += ($precio_bs_item * $cant_item);
    }
    
    // Cálculo del IVA en USD basado en los productos configurados
    $monto_iva_usd = 0;
    if ($usar_iva_global === 1) {$monto_iva_usd = $base_imponible_iva_usd * ($porcentaje_iva / 100);
    }

    // TRUNCADO A 2 DECIMALES (Incluyendo subproductos, IVA y avance)
    $total_final    = truncar2($total_productos_usd +$monto_iva_usd + $avance_usd);$total_venta_bs = truncar2(($total_productos_usd +$monto_iva_usd + $avance_usd) *$tasa);

    $json_detalles = !empty($_POST['detalle_pagos_multiples']) ? json_decode($_POST['detalle_pagos_multiples'], true) : null;
    $es_cashea     = ($metodo_pago === 'Cashea' ||$monto_financiado > 0 || (isset($json_detalles['metodo_pago']) &&$json_detalles['metodo_pago'] === 'Cashea'));

    // 3. ASIGNACIÓN MATEMÁTICA Y CASHEA
    if ($es_cashea) {$metodo_pago = 'Cashea';
        
        if ($monto_recargo == 0 &&$json_detalles)   $monto_recargo = limpiarNumero($json_detalles['recargo_usd'] ?? 0);
        if ($monto_inicial == 0 &&$json_detalles)   $monto_inicial = limpiarNumero($json_detalles['inicial_usd'] ?? 0);
        if ($monto_financiado == 0 &&$json_detalles) $monto_financiado = limpiarNumero($json_detalles['financiamiento_cashea_usd'] ?? 0);

        // Agregamos el recargo al total definitivo de la transacción
        $total_final = truncar2($total_final +$monto_recargo);
        $total_venta_bs = truncar2($total_venta_bs + ($monto_recargo * $tasa));

        // Registrar la inicial en las cajas de corte
        if ($p_usd == 0 && $p_bs == 0 &&$p_punto == 0 && $p_movil == 0 &&$p_biopago == 0 && $monto_inicial > 0) {$metodo_inicial = $json_detalles['metodo_inicial'] ?? 'Efectivo$';
            if ($metodo_inicial === 'Efectivo$' || $metodo_inicial === 'Dolares$') {
                $p_usd =$monto_inicial;
            } elseif ($metodo_inicial === 'Pago Móvil') {
                $p_movil = truncar2($monto_inicial * $tasa);
            } elseif ($metodo_inicial === 'Punto') {$p_punto = truncar2($monto_inicial * $tasa);
            } elseif ($metodo_inicial === 'Efectivo Bs.') {$p_bs = truncar2($monto_inicial * $tasa);
            } elseif ($metodo_inicial === 'BioPago') {$p_biopago = truncar2($monto_inicial * $tasa);
            }
        }

    } elseif ($metodo_pago === 'Multiple' || $metodo_pago === 'Múltiple' || $metodo_pago === 'Pago Múltiple') {
        if ($json_detalles) {
            $p_bs      = limpiarNumero($json_detalles['efectivo_bs'] ?? 0);
            $p_usd     = limpiarNumero($json_detalles['efectivo_usd'] ?? 0);
            $p_punto   = limpiarNumero($json_detalles['punto_bs'] ?? 0);
            $p_movil   = limpiarNumero($json_detalles['pagomovil_bs'] ?? 0);
            $p_biopago = limpiarNumero($json_detalles['biopago_bs'] ?? 0);
        }
    } else {
        if ($metodo_pago == 'Pago Móvil' && $p_movil == 0) {
            $p_movil =$total_venta_bs;
        } elseif ($metodo_pago == 'BioPago' &&$p_biopago == 0) {
            $p_biopago =$total_venta_bs;
        } elseif ($metodo_pago == 'Punto' &&$p_punto == 0) {
            $p_punto =$total_venta_bs;
        } elseif ($metodo_pago == 'Efectivo Bs.' &&$p_bs == 0) {
            $p_bs =$total_venta_bs;
        } elseif (($metodo_pago == 'Dolares $' ||$metodo_pago == 'Efectivo $') &&$p_usd == 0) {
            $p_usd =$total_final;
        }
    }

    // Suma de todos los métodos abonados (Truncado a 2 decimales)
    $pago_total_cliente_bs = truncar2(($p_usd * $tasa) + $p_bs +$p_punto + $p_movil +$p_biopago);
    $pago_total_cliente_usd = ($tasa > 0) ? truncar2($pago_total_cliente_bs / $tasa) : 0;

    $v_bs = ($pago_total_cliente_bs > ($total_venta_bs - 0.01) && !$es_cashea &&$metodo_pago !== 'Crédito') ? truncar2($pago_total_cliente_bs -$total_venta_bs) : 0;
    $v_usd = ($tasa > 0) ? truncar2($v_bs / $tasa) : 0;

    mysqli_begin_transaction($conexion);

    try {
        $total_f     = number_format($total_final, 2, '.', '');
        $p_usd_f     = number_format($p_usd, 2, '.', '');
        $p_bs_f      = number_format($p_bs, 2, '.', '');
        $p_punto_f   = number_format($p_punto, 2, '.', '');
        $p_movil_f   = number_format($p_movil, 2, '.', ''); 
        $p_biopago_f = number_format($p_biopago, 2, '.', '');
        $v_bs_f      = number_format($v_bs, 2, '.', '');
        $v_usd_f     = number_format($v_usd, 2, '.', '');
        $tasa_f      = number_format($tasa, 4, '.', ''); 

        // 1. Insertar venta principal con el total que incluye IVA[cite: 9]
        $ins_venta = "INSERT INTO ventas (id_cliente, cliente, total, metodo_pago, pago_efectivo_usd, pago_efectivo_bs, pago_punto_bs, pago_movil_bs, pago_biopago_bs, cambio_bs, cambio_usd, tasa_momento, fecha, estado, usuario) 
                      VALUES ($id_val, '$nombre_cliente', '$total_f', '$metodo_pago', '$p_usd_f', '$p_bs_f', '$p_punto_f', '$p_movil_f', '$p_biopago_f', '$v_bs_f', '$v_usd_f', '$tasa_f', '$fecha_actual', 'PAGADA', '$usuario_activo')";
        
        if (!mysqli_query($conexion,$ins_venta)) {
            throw new Exception("Error al insertar venta: " . mysqli_error($conexion));
        }
        
        $id_venta = mysqli_insert_id($conexion);

        // =========================================================================
        // 1.1 REGISTRO DE PAGO MÓVIL EN LA TABLA 'registro_pago_movil'
        // =========================================================================
        if ($p_movil > 0) {$banco_origen = !empty($_POST['pm_banco_emisor']) ? mysqli_real_escape_string($conexion, $_POST['pm_banco_emisor']) : ($json_detalles['pm_banco_emisor'] ?? 'No especificado');
            $referencia   = !empty($_POST['pm_referencia']) ? mysqli_real_escape_string($conexion,$_POST['pm_referencia']) : ($json_detalles['pm_referencia'] ?? 'S/N');$ins_pm = "INSERT INTO registro_pago_movil (id_venta, banco_origen, referencia, monto_bs, usuario, fecha) 
                       VALUES ('$id_venta', '$banco_origen', '$referencia', '$p_movil_f', '$usuario_activo', '$fecha_actual')";
            
            if (!mysqli_query($conexion,$ins_pm)) {
                throw new Exception("Error al registrar Pago Móvil: " . mysqli_error($conexion));
            }
        }
        // =========================================================================

        // 2. Desglose Cashea
        if ($es_cashea) {
            if ($monto_financiado <= 0) {
                $monto_inicial_real_pagado =$p_usd + (($p_bs +$p_punto + $p_movil +$p_biopago) / $tasa);$monto_financiado = truncar2($total_final -$monto_inicial_real_pagado);
                $monto_inicial = truncar2($monto_inicial_real_pagado);
            }

            $mf_f = number_format($monto_financiado, 2, '.', '');
            $mi_f = number_format($monto_inicial, 2, '.', '');
            $mr_f = number_format($monto_recargo, 2, '.', '');

            $ins_cashea = "INSERT INTO ventas_cashea (id_venta, monto_financiado, monto_inicial, monto_recargo) 
                           VALUES ('$id_venta', '$mf_f', '$mi_f', '$mr_f')";
            
            if (!mysqli_query($conexion,$ins_cashea)) {
                throw new Exception("Error al guardar desglose de Cashea: " . mysqli_error($conexion));
            }
        }

        // 3. Registrar artículos en el detalle
        foreach ($carrito as $prod) {$id_p        = (int)$prod['id'];$cant        = limpiarNumero($prod['cantidad']);$precio_base = limpiarNumero($prod['precio']);$desc        = limpiarNumero($prod['descuento'] ?? 0);$nombre_p    = mysqli_real_escape_string($conexion,$prod['nombre']);

            $precio_con_descuento = $precio_base * (1 - ($desc / 100));

            $precio_final_usd =$precio_con_descuento;
            $desc_final_db    =$desc;
            $detalle_h        = "Venta Factura #$id_venta - Producto: $nombre_p" . ($es_cashea ? " (Cashea)" : "");

            $precio_f = number_format($precio_final_usd, 2, '.', '');
            $desc_f   = number_format($desc_final_db, 2, '.', '');

            $ins_detalle = "INSERT INTO detalle_ventas (id_venta, id_producto, cantidad, precio, descuento) 
                            VALUES ('$id_venta', '$id_p', '$cant', '$precio_f', '$desc_f')";
            
            if (!mysqli_query($conexion,$ins_detalle)) {
                throw new Exception("Error en detalle: " . mysqli_error($conexion));
            }

            // Actualizar Inventario
            mysqli_query($conexion, "UPDATE productos SET stock = stock - $cant WHERE id =$id_p");
            mysqli_query($conexion, "INSERT INTO historial_movimientos (id_producto, tipo_movimiento, cantidad, detalle, usuario, fecha) 
                                     VALUES ('$id_p', 'VENTA', '$cant', '$detalle_h', '$usuario_activo', '$fecha_actual')");
        }

        // Avances de efectivo
        if ($avance_usd > 0) {
            $av_f = number_format($avance_usd, 2, '.', '');
            mysqli_query($conexion, "INSERT INTO detalle_ventas (id_venta, id_producto, cantidad, precio, descuento) VALUES ('$id_venta', 0, 1, '$av_f', '0')");
        }

        if ($metodo_pago === 'Crédito' && $id_val !== "NULL") {
            mysqli_query($conexion, "UPDATE clientes_credito SET deuda_actual = deuda_actual + '$total_f' WHERE id =$id_val");
        }

        mysqli_commit($conexion);
        
        // Formateo de valores para el modal SweetAlert2
        $pago_total_bs_format  = number_format($pago_total_cliente_bs, 2, ',', '.');
        $pago_total_usd_format = number_format($pago_total_cliente_usd, 2, ',', '.');
        $v_bs_format           = number_format($v_bs, 2, ',', '.');
        $v_usd_format          = number_format($v_usd, 2, ',', '.');
        $total_venta_bs_format = number_format($total_venta_bs, 2, ',', '.');
        $total_final_format    = number_format($total_final, 2, ',', '.');

        echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body>
            <script>
                Swal.fire({
                    title: '¡Venta Exitosa!',
                    html: `
                        <div style='text-align: center; font-size: 15px; line-height: 1.6;'>
                            <p><b>Venta #$id_venta</b> registrada correctamente.</p>
                        </div>
                    `,
                    icon: 'success',
                    showCancelButton: true,
                    confirmButtonText: '🖨️ Ticket',
                    cancelButtonText: '✅ Continuar',
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#10b981',
                    allowOutsideClick: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.open('imprimir_factura.php?id=$id_venta', '_blank');
                    }
                    window.location.href = 'ventas.php';
                });
            </script></body></html>";
        exit();

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.location.href = 'ventas.php';</script>";
        exit();
    }
}
?>