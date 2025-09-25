<?php
/**
 * Plugin Name:       Precio en Cuotas Simple
 * Plugin URI:        https://github.com/Victoria-Mondino/precio-en-cuotas.git
 * Description:       Muestra el precio en cuotas de cada producto, configurable globalmente o por producto.
 * Version:           1.0.0
 * Author:            Victoria Mondino
 * Author URI:        https://github.com/Victoria-Mondino/
 * License:           GPL-2.0+
 * Text Domain:       precio-en-cuotas
 */

// ==========================
// OPCIÓN GLOBAL DE CUOTAS
// ==========================
add_action('admin_menu', function() {
    add_menu_page(
        'Configuración de Cuotas',
        'Cuotas',
        'manage_options',
        'precio-cuotas',
        function() {
            if (isset($_POST['num_cuotas'])) {
                update_option('precio_cuotas_global', intval($_POST['num_cuotas']));
                echo '<div class="updated"><p>Guardado correctamente.</p></div>';
            }
            $num_cuotas = get_option('precio_cuotas_global', 3);
            ?>
            <div class="wrap">
                <h1>Configuración de Cuotas Global</h1>
                <form method="POST">
                    <label>Cantidad de cuotas por defecto:</label>
                    <input type="number" name="num_cuotas" value="<?php echo esc_attr($num_cuotas); ?>" min="1" />
                    <input type="submit" class="button button-primary" value="Guardar" />
                </form>
            </div>
            <?php
        }
    );
});

// ==========================
// CAMPO DE CUOTAS POR PRODUCTO
// ==========================
add_action('woocommerce_product_options_pricing', function() {
    woocommerce_wp_text_input(array(
        'id' => '_num_cuotas',
        'label' => __('Cantidad de cuotas', 'precio-en-cuotas'),
        'desc_tip' => 'true',
        'description' => __('Cantidad de cuotas para este producto (si está vacío se usará la global)', 'precio-en-cuotas'),
        'type' => 'number',
        'custom_attributes' => array(
            'min' => '1',
            'step' => '1'
        )
    ));
});

// Guardar valor por producto
add_action('woocommerce_process_product_meta', function($post_id) {
    $num_cuotas = isset($_POST['_num_cuotas']) ? intval($_POST['_num_cuotas']) : '';
    update_post_meta($post_id, '_num_cuotas', $num_cuotas);
});

// ==========================
// MOSTRAR PRECIO EN CUOTAS
// ==========================
add_filter('woocommerce_get_price_html', function($price, $product) {

    // Valor individual del producto
    $num_cuotas = get_post_meta($product->get_id(), '_num_cuotas', true);

    // Si no hay valor individual, tomar global
    if (!$num_cuotas) {
        $num_cuotas = get_option('precio_cuotas_global', 3);
    }

    if ($num_cuotas > 1) {
        $precio = $product->get_price();
        $precio_cuota = $precio / $num_cuotas;

        // Se agrega texto simple debajo del precio sin cambiar estilos
        $price .= sprintf('<br>%d cuotas de $%s', $num_cuotas, number_format($precio_cuota, 2, ',', '.'));
    }

    return $price;

}, 10, 2);
