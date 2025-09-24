<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://https://github.com/Victoria-Mondino
 * @since             1.0.0
 * @package           Precio_En_Cuotas
 *
 * @wordpress-plugin
 * Plugin Name:       Precio En Cuotas
 * Plugin URI:        https://github.com/Victoria-Mondino/precio-en-cuotas.git
 * Description:       This is a description of the plugin.
 * Version:           1.0.0
 * Author:            Victoria Mondino
 * Author URI:        https://https://github.com/Victoria-Mondino/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       precio-en-cuotas
 * Domain Path:       /languages
 */


// Crear opción para cantidad de cuotas en admin
add_action('admin_menu', function() {
    add_menu_page(
        'Configuración de Cuotas',
        'Cuotas',
        'manage_options',
        'precio-cuotas',
        function() {
            // Guardar opción
            if (isset($_POST['num_cuotas'])) {
                update_option('precio_cuotas_num', intval($_POST['num_cuotas']));
                echo '<div class="updated"><p>Guardado correctamente.</p></div>';
            }
            $num_cuotas = get_option('precio_cuotas_num', 3);
            ?>
            <div class="wrap">
                <h1>Configuración de Cuotas</h1>
                <form method="POST">
                    <label>Cantidad de cuotas:</label>
                    <input type="number" name="num_cuotas" value="<?php echo esc_attr($num_cuotas); ?>" min="1" />
                    <input type="submit" class="button button-primary" value="Guardar" />
                </form>
            </div>
            <?php
        }
    );
});

// Mostrar precio en cuotas en tienda y producto individual
add_filter('woocommerce_get_price_html', function($price, $product) {
    $num_cuotas = get_option('precio_cuotas_num', 3); // Tomar opción del admin
    if ($num_cuotas > 1) {
        $precio = $product->get_price();
        $precio_cuota = $precio / $num_cuotas;
        $price .= sprintf(' <br><small>%d cuotas de $%s</small>', $num_cuotas, number_format($precio_cuota, 2, ',', '.'));
    }
    return $price;
}, 10, 2);
