<?php
/**
 * Plugin Name:       Precio en Cuotas Simple
 * Plugin URI:        https://github.com/Victoria-Mondino/precio-en-cuotas.git
 * Description:       Muestra el precio en cuotas de cada producto, configurable globalmente o por producto.
 * Version:           1.0.2
 * Author:            Victoria Mondino
 * Author URI:        https://github.com/Victoria-Mondino/
 * License:           GPL-2.0+
 * Text Domain:       precio-en-cuotas
 */

// Evitar acceso directo
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Crear página de administración para la cantidad de cuotas global
 */
add_action( 'admin_menu', function() {
	add_menu_page(
		__( 'Configuración de Cuotas', 'precio-en-cuotas' ),
		__( 'Cuotas', 'precio-en-cuotas' ),
		'manage_options',
		'precio-cuotas',
		function() {
			// Guardado seguro con nonce
			if ( isset( $_POST['precio_cuotas_nonce'] ) && wp_verify_nonce( $_POST['precio_cuotas_nonce'], 'guardar_precio_cuotas' ) ) {
				$num = isset( $_POST['num_cuotas'] ) ? intval( $_POST['num_cuotas'] ) : 0;
				$num = max( 1, $num ); // al menos 1
				update_option( 'precio_cuotas_global', $num );
				echo '<div class="updated"><p>' . esc_html__( 'Guardado correctamente.', 'precio-en-cuotas' ) . '</p></div>';
			}

			$num_cuotas = intval( get_option( 'precio_cuotas_global', 3 ) ); ?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Configuración de Cuotas Global', 'precio-en-cuotas' ); ?></h1>
				<form method="POST">
					<?php wp_nonce_field( 'guardar_precio_cuotas', 'precio_cuotas_nonce' ); ?>
					<p>
						<label for="num_cuotas"><?php esc_html_e( 'Cantidad de cuotas por defecto:', 'precio-en-cuotas' ); ?></label><br/>
						<input type="number" id="num_cuotas" name="num_cuotas" value="<?php echo esc_attr( $num_cuotas ); ?>" min="1" />
					</p>
					<p><input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Guardar', 'precio-en-cuotas' ); ?>" /></p>
				</form>
			</div>
			<?php
		},
		'dashicons-admin-generic',
		60
	);
} );

/**
 * Campo en la ficha del producto (pricing)
 */
add_action( 'woocommerce_product_options_pricing', function() {
	woocommerce_wp_text_input( array(
		'id'                => '_num_cuotas',
		'label'             => __( 'Cantidad de cuotas', 'precio-en-cuotas' ),
		'desc_tip'          => 'true',
		'description'       => __( 'Cantidad de cuotas para este producto (si está vacío se usará la global)', 'precio-en-cuotas' ),
		'type'              => 'number',
		'custom_attributes' => array(
			'min'  => '1',
			'step' => '1',
		),
	) );
} );

/**
 * Guardar campo por producto
 */
add_action( 'woocommerce_process_product_meta', function( $post_id ) {
	if ( isset( $_POST['_num_cuotas'] ) ) {
		$val = intval( $_POST['_num_cuotas'] );
		if ( $val < 1 ) {
			// Si viene vacío o inválido, guardamos vacío para usar la global
			delete_post_meta( $post_id, '_num_cuotas' );
		} else {
			update_post_meta( $post_id, '_num_cuotas', $val );
		}
	}
} );

/**
 * Función auxiliar: obtener número de cuotas (producto -> meta -> global)
 * @param WC_Product|int $product
 * @return int
 */
function pec_obtener_num_cuotas( $product ) {
	$id = is_object( $product ) && method_exists( $product, 'get_id' ) ? $product->get_id() : intval( $product );
	$meta = intval( get_post_meta( $id, '_num_cuotas', true ) );
	if ( $meta >= 1 ) {
		return $meta;
	}
	$global = intval( get_option( 'precio_cuotas_global', 3 ) );
	return max( 1, $global );
}

/**
 * Mostrar precio en cuotas: usamos wc_get_price_to_display y wc_price para formatear correctamente (impuestos/moneda)
 *
 * Hook al filtro que muestra el HTML del precio (se aplica tanto en loop como single en la mayoría de themes/WC).
 */
add_filter( 'woocommerce_get_price_html', function( $price_html, $product ) {

	// Validar objeto producto
	if ( ! is_object( $product ) || ! method_exists( $product, 'get_price' ) ) {
		return $price_html;
	}

	// Obtener número de cuotas válido
	$num_cuotas = pec_obtener_num_cuotas( $product );
	if ( $num_cuotas <= 1 ) {
		return $price_html;
	}

	// Obtener el precio de visualización (incluye reglas de impuestos según configuración WC)
	$raw_price = $product->get_price();

	// Si no hay precio, intentar con regular price (evita nulls)
	if ( $raw_price === '' || is_null( $raw_price ) ) {
		$raw_price = $product->get_regular_price();
	}

	$precio_display = 0.0;
	if ( $raw_price !== '' && $raw_price !== null ) {
		// wc_get_price_to_display maneja impuestos y display rules
		$precio_display = floatval( wc_get_price_to_display( $product, array( 'price' => $raw_price ) ) );
	}

	// Si no hay precio positivo, no mostramos nada
	if ( $precio_display <= 0 ) {
		return $price_html;
	}

	// Calcular cuota
	$precio_cuota = $precio_display / max( 1, $num_cuotas );

	// Formatear con wc_price para respetar moneda y formato
	$precio_cuota_formateado = wc_price( $precio_cuota );

	// Texto final (multilenguaje)
	$text = sprintf( /* translators: 1: number of installments, 2: installment price */ 
		__( '%1$d cuotas de %2$s', 'precio-en-cuotas' ),
		intval( $num_cuotas ),
		$precio_cuota_formateado
	);

	// Envolver en small para no romper diseño; el tema puede sobrescribir estilos
	$price_html .= '<br><small class="precio-en-cuotas">' . wp_kses_post( $text ) . '</small>';

	return $price_html;

}, 10, 2 );
