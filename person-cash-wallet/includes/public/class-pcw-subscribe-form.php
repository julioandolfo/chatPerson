<?php
/**
 * Formulário de inscrição nativo
 *
 * @package PersonCashWallet
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de formulário de inscrição
 */
class PCW_Subscribe_Form {

	/**
	 * Instância singleton
	 *
	 * @var PCW_Subscribe_Form
	 */
	private static $instance = null;

	/**
	 * Obter instância
	 *
	 * @return PCW_Subscribe_Form
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Inicializar
	 */
	public function init() {
		// Registrar shortcode
		add_shortcode( 'pcw_subscribe_form', array( $this, 'render_shortcode' ) );

		// Registrar widget
		add_action( 'widgets_init', array( $this, 'register_widget' ) );

		// Handler AJAX para submissão
		add_action( 'wp_ajax_pcw_subscribe', array( $this, 'handle_submission' ) );
		add_action( 'wp_ajax_nopriv_pcw_subscribe', array( $this, 'handle_submission' ) );

		// Enfileirar assets
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enfileirar assets
	 */
	public function enqueue_assets() {
		if ( ! wp_script_is( 'pcw-subscribe-form', 'registered' ) ) {
			wp_register_style(
				'pcw-subscribe-form',
				PCW_PLUGIN_URL . 'assets/css/subscribe-form.css',
				array(),
				PCW_VERSION
			);

			wp_register_script(
				'pcw-subscribe-form',
				PCW_PLUGIN_URL . 'assets/js/subscribe-form.js',
				array( 'jquery' ),
				PCW_VERSION,
				true
			);

			wp_localize_script(
				'pcw-subscribe-form',
				'pcwSubscribeForm',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'pcw_subscribe' ),
					'messages' => array(
						'success' => __( 'Obrigado por se inscrever!', 'person-cash-wallet' ),
						'error'   => __( 'Erro ao processar inscrição. Tente novamente.', 'person-cash-wallet' ),
						'invalid_email' => __( 'Email inválido', 'person-cash-wallet' ),
					),
				)
			);
		}
	}

	/**
	 * Renderizar shortcode
	 *
	 * @param array $atts Atributos do shortcode.
	 * @return string HTML do formulário.
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'list_id'       => '',
				'automation_id' => '',
				'title'         => __( 'Inscreva-se', 'person-cash-wallet' ),
				'subtitle'      => __( 'Receba novidades e ofertas exclusivas', 'person-cash-wallet' ),
				'button_text'   => __( 'Inscrever', 'person-cash-wallet' ),
				'show_name'     => 'yes',
				'show_phone'    => 'no',
				'style'         => 'default', // default, minimal, inline
				'redirect_url'  => '',
			),
			$atts,
			'pcw_subscribe_form'
		);

		// Enfileirar assets
		wp_enqueue_style( 'pcw-subscribe-form' );
		wp_enqueue_script( 'pcw-subscribe-form' );

		ob_start();
		?>
		<div class="pcw-subscribe-form pcw-subscribe-form--<?php echo esc_attr( $atts['style'] ); ?>">
			<?php if ( ! empty( $atts['title'] ) ) : ?>
				<h3 class="pcw-subscribe-form__title"><?php echo esc_html( $atts['title'] ); ?></h3>
			<?php endif; ?>

			<?php if ( ! empty( $atts['subtitle'] ) ) : ?>
				<p class="pcw-subscribe-form__subtitle"><?php echo esc_html( $atts['subtitle'] ); ?></p>
			<?php endif; ?>

			<form class="pcw-subscribe-form__form" method="post" data-list-id="<?php echo esc_attr( $atts['list_id'] ); ?>" data-automation-id="<?php echo esc_attr( $atts['automation_id'] ); ?>" data-redirect="<?php echo esc_url( $atts['redirect_url'] ); ?>">
				
				<?php if ( 'yes' === $atts['show_name'] ) : ?>
					<div class="pcw-subscribe-form__field">
						<label for="pcw_subscribe_name"><?php esc_html_e( 'Nome', 'person-cash-wallet' ); ?></label>
						<input 
							type="text" 
							id="pcw_subscribe_name" 
							name="name" 
							placeholder="<?php esc_attr_e( 'Seu nome', 'person-cash-wallet' ); ?>"
							required
						/>
					</div>
				<?php endif; ?>

				<div class="pcw-subscribe-form__field">
					<label for="pcw_subscribe_email"><?php esc_html_e( 'Email', 'person-cash-wallet' ); ?></label>
					<input 
						type="email" 
						id="pcw_subscribe_email" 
						name="email" 
						placeholder="<?php esc_attr_e( 'seu@email.com', 'person-cash-wallet' ); ?>"
						required
					/>
				</div>

				<?php if ( 'yes' === $atts['show_phone'] ) : ?>
					<div class="pcw-subscribe-form__field">
						<label for="pcw_subscribe_phone"><?php esc_html_e( 'Telefone', 'person-cash-wallet' ); ?></label>
						<input 
							type="tel" 
							id="pcw_subscribe_phone" 
							name="phone" 
							placeholder="<?php esc_attr_e( '(00) 00000-0000', 'person-cash-wallet' ); ?>"
						/>
					</div>
				<?php endif; ?>

				<?php wp_nonce_field( 'pcw_subscribe', 'pcw_subscribe_nonce' ); ?>

				<button type="submit" class="pcw-subscribe-form__submit">
					<?php echo esc_html( $atts['button_text'] ); ?>
				</button>

				<div class="pcw-subscribe-form__message" style="display:none;"></div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Processar submissão do formulário
	 */
	public function handle_submission() {
		// Verificar nonce
		if ( ! isset( $_POST['pcw_subscribe_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pcw_subscribe_nonce'] ) ), 'pcw_subscribe' ) ) {
			wp_send_json_error( array( 'message' => __( 'Ação não autorizada', 'person-cash-wallet' ) ) );
		}

		// Validar email
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Email inválido', 'person-cash-wallet' ) ) );
		}

		// Dados do formulário
		$data = array(
			'email' => $email,
			'name'  => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'phone' => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
		);

		$list_id = isset( $_POST['list_id'] ) ? absint( $_POST['list_id'] ) : 0;
		$automation_id = isset( $_POST['automation_id'] ) ? absint( $_POST['automation_id'] ) : 0;

		// Buscar ou criar usuário
		$user = get_user_by( 'email', $email );
		$user_id = $user ? $user->ID : null;

		// Adicionar à lista se especificada
		if ( $list_id > 0 ) {
			$members = array(
				array(
					'email' => $data['email'],
					'name'  => $data['name'],
					'phone' => $data['phone'],
					'metadata' => array(
						'source'   => 'pcw_subscribe_form',
						'added_at' => current_time( 'mysql' ),
					),
				),
			);

			$result = PCW_Custom_Lists::add_members( $list_id, $members );

			if ( isset( $result['errors'] ) && ! empty( $result['errors'] ) ) {
				wp_send_json_error( array( 'message' => implode( ', ', $result['errors'] ) ) );
			}
		}

		// Disparar hook customizado
		do_action( 'pcw_new_subscriber', $data['email'], $data['name'], array(
			'phone'         => $data['phone'],
			'user_id'       => $user_id,
			'list_id'       => $list_id,
			'automation_id' => $automation_id,
			'source'        => 'pcw_subscribe_form',
		) );

		wp_send_json_success( array(
			'message' => __( 'Obrigado por se inscrever!', 'person-cash-wallet' ),
		) );
	}

	/**
	 * Registrar widget
	 */
	public function register_widget() {
		register_widget( 'PCW_Subscribe_Widget' );
	}
}

/**
 * Widget de inscrição
 */
class PCW_Subscribe_Widget extends WP_Widget {

	/**
	 * Construtor
	 */
	public function __construct() {
		parent::__construct(
			'pcw_subscribe_widget',
			__( 'PCW: Formulário de Inscrição', 'person-cash-wallet' ),
			array( 'description' => __( 'Formulário de inscrição em newsletter', 'person-cash-wallet' ) )
		);
	}

	/**
	 * Renderizar widget
	 *
	 * @param array $args Argumentos do widget.
	 * @param array $instance Instância do widget.
	 */
	public function widget( $args, $instance ) {
		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$shortcode_atts = array(
			'list_id'       => isset( $instance['list_id'] ) ? $instance['list_id'] : '',
			'automation_id' => isset( $instance['automation_id'] ) ? $instance['automation_id'] : '',
			'title'         => isset( $instance['title'] ) ? $instance['title'] : '',
			'subtitle'      => isset( $instance['subtitle'] ) ? $instance['subtitle'] : '',
			'button_text'   => isset( $instance['button_text'] ) ? $instance['button_text'] : '',
			'show_name'     => isset( $instance['show_name'] ) ? $instance['show_name'] : 'yes',
			'show_phone'    => isset( $instance['show_phone'] ) ? $instance['show_phone'] : 'no',
			'style'         => isset( $instance['style'] ) ? $instance['style'] : 'default',
		);

		echo PCW_Subscribe_Form::instance()->render_shortcode( $shortcode_atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Formulário de configuração do widget
	 *
	 * @param array $instance Instância do widget.
	 */
	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : __( 'Inscreva-se', 'person-cash-wallet' );
		$subtitle = isset( $instance['subtitle'] ) ? $instance['subtitle'] : '';
		$button_text = isset( $instance['button_text'] ) ? $instance['button_text'] : __( 'Inscrever', 'person-cash-wallet' );
		$list_id = isset( $instance['list_id'] ) ? $instance['list_id'] : '';
		$automation_id = isset( $instance['automation_id'] ) ? $instance['automation_id'] : '';
		$show_name = isset( $instance['show_name'] ) ? $instance['show_name'] : 'yes';
		$show_phone = isset( $instance['show_phone'] ) ? $instance['show_phone'] : 'no';
		$style = isset( $instance['style'] ) ? $instance['style'] : 'default';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Título:', 'person-cash-wallet' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'subtitle' ) ); ?>"><?php esc_html_e( 'Subtítulo:', 'person-cash-wallet' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'subtitle' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'subtitle' ) ); ?>" type="text" value="<?php echo esc_attr( $subtitle ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'button_text' ) ); ?>"><?php esc_html_e( 'Texto do Botão:', 'person-cash-wallet' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'button_text' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'button_text' ) ); ?>" type="text" value="<?php echo esc_attr( $button_text ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'list_id' ) ); ?>"><?php esc_html_e( 'ID da Lista:', 'person-cash-wallet' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'list_id' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'list_id' ) ); ?>" type="number" value="<?php echo esc_attr( $list_id ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'automation_id' ) ); ?>"><?php esc_html_e( 'ID da Automação:', 'person-cash-wallet' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'automation_id' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'automation_id' ) ); ?>" type="number" value="<?php echo esc_attr( $automation_id ); ?>" />
		</p>
		<p>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'show_name' ) ); ?>" value="yes" <?php checked( $show_name, 'yes' ); ?> />
				<?php esc_html_e( 'Mostrar campo Nome', 'person-cash-wallet' ); ?>
			</label>
		</p>
		<p>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'show_phone' ) ); ?>" value="yes" <?php checked( $show_phone, 'yes' ); ?> />
				<?php esc_html_e( 'Mostrar campo Telefone', 'person-cash-wallet' ); ?>
			</label>
		</p>
		<?php
	}

	/**
	 * Atualizar widget
	 *
	 * @param array $new_instance Nova instância.
	 * @param array $old_instance Instância antiga.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ! empty( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['subtitle'] = ! empty( $new_instance['subtitle'] ) ? sanitize_text_field( $new_instance['subtitle'] ) : '';
		$instance['button_text'] = ! empty( $new_instance['button_text'] ) ? sanitize_text_field( $new_instance['button_text'] ) : '';
		$instance['list_id'] = ! empty( $new_instance['list_id'] ) ? absint( $new_instance['list_id'] ) : '';
		$instance['automation_id'] = ! empty( $new_instance['automation_id'] ) ? absint( $new_instance['automation_id'] ) : '';
		$instance['show_name'] = ! empty( $new_instance['show_name'] ) ? 'yes' : 'no';
		$instance['show_phone'] = ! empty( $new_instance['show_phone'] ) ? 'yes' : 'no';
		$instance['style'] = isset( $new_instance['style'] ) ? sanitize_text_field( $new_instance['style'] ) : 'default';

		return $instance;
	}
}
