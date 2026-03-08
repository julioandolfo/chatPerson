<?php
/**
 * Integração com OpenAI
 *
 * @package GrowlyDigital
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de integração com OpenAI
 */
class PCW_OpenAI {

	/**
	 * Instância singleton
	 *
	 * @var PCW_OpenAI
	 */
	private static $instance = null;

	/**
	 * API Key
	 *
	 * @var string
	 */
	private $api_key = '';

	/**
	 * Modelo padrão
	 *
	 * @var string
	 */
	private $model = 'gpt-4o-mini';

	/**
	 * Obter instância
	 *
	 * @return PCW_OpenAI
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Construtor
	 */
	private function __construct() {
		$this->api_key = get_option( 'pcw_openai_api_key', '' );
		$this->model = get_option( 'pcw_openai_model', 'gpt-4o-mini' );
	}

	/**
	 * Verificar se está configurado
	 *
	 * @return bool
	 */
	public function is_configured() {
		return ! empty( $this->api_key );
	}

	/**
	 * Obter modelos disponíveis
	 *
	 * @return array
	 */
	public static function get_available_models() {
		return array(
			'gpt-4o'        => 'GPT-4o (Mais inteligente)',
			'gpt-4o-mini'   => 'GPT-4o Mini (Rápido e econômico)',
			'gpt-4-turbo'   => 'GPT-4 Turbo',
			'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Mais econômico)',
		);
	}

	/**
	 * Fazer requisição à API
	 *
	 * @param string $endpoint Endpoint.
	 * @param array  $data Dados.
	 * @return array|WP_Error
	 */
	private function request( $endpoint, $data ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'no_api_key', __( 'API Key da OpenAI não configurada', 'person-cash-wallet' ) );
		}

		$response = wp_remote_post(
			'https://api.openai.com/v1/' . $endpoint,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $data ),
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new WP_Error(
				'openai_error',
				isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Erro na API OpenAI', 'person-cash-wallet' )
			);
		}

		return $body;
	}

	/**
	 * Gerar texto com chat completion
	 *
	 * @param string $prompt Prompt do usuário.
	 * @param string $system_prompt Prompt do sistema.
	 * @param array  $options Opções adicionais.
	 * @return string|WP_Error
	 */
	public function generate_text( $prompt, $system_prompt = '', $options = array() ) {
		$messages = array();

		if ( ! empty( $system_prompt ) ) {
			$messages[] = array(
				'role'    => 'system',
				'content' => $system_prompt,
			);
		}

		$messages[] = array(
			'role'    => 'user',
			'content' => $prompt,
		);

		$data = array(
			'model'       => isset( $options['model'] ) ? $options['model'] : $this->model,
			'messages'    => $messages,
			'temperature' => isset( $options['temperature'] ) ? $options['temperature'] : 0.7,
			'max_tokens'  => isset( $options['max_tokens'] ) ? $options['max_tokens'] : 500,
		);

		$response = $this->request( 'chat/completions', $data );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( isset( $response['choices'][0]['message']['content'] ) ) {
			return trim( $response['choices'][0]['message']['content'] );
		}

		return new WP_Error( 'no_content', __( 'Resposta sem conteúdo', 'person-cash-wallet' ) );
	}

	/**
	 * Gerar assunto de email com IA
	 *
	 * @param string $context Contexto do email.
	 * @param string $type Tipo de email.
	 * @param array  $variables Variáveis disponíveis.
	 * @return string|WP_Error
	 */
	public function generate_email_subject( $context, $type = 'marketing', $variables = array() ) {
		$system_prompt = "Você é um especialista em email marketing para e-commerce brasileiro. 
Sua tarefa é criar linhas de assunto (subject lines) que MAXIMIZAM a taxa de abertura.

Regras OBRIGATÓRIAS:
- Máximo de 50-60 caracteres (curto e impactante)
- Use 1-2 emojis ESTRATÉGICOS no início ou final (💰 🎉 ⭐ 🔥 ⏰ 🎁 👀 etc)
- Crie URGÊNCIA ou CURIOSIDADE forte
- Seja PESSOAL e direto com o cliente
- Use números quando relevante (ex: '50% OFF', 'R$ 100')
- Use palavras de PODER: exclusivo, hoje, última chance, você ganhou, parabéns
- Português brasileiro natural e coloquial
- NÃO use CAPS LOCK total
- NÃO use palavras spam: grátis sozinho, ganhe dinheiro, clique aqui
- Foque no BENEFÍCIO do cliente, não na empresa

FÓRMULAS que funcionam:
- Urgência: '⏰ Última chance: [benefício]'
- Curiosidade: '👀 Você não vai acreditar...'
- Personalização: '🎁 [Nome], isso é só para você'
- Prova social: '⭐ +1000 clientes já aproveitaram'
- Oferta: '💰 R$ [valor] de desconto expira hoje'

Variáveis disponíveis: " . implode( ', ', array_keys( $variables ) );

		$prompt = "Crie UM assunto IMPACTANTE para email: {$type}

Contexto e dados da loja: {$context}

IMPORTANTE: Crie um assunto que faça o cliente QUERER abrir o email imediatamente.
Use gatilhos mentais de urgência, exclusividade ou curiosidade.

Retorne APENAS o assunto, sem aspas ou explicações.";

		return $this->generate_text( $prompt, $system_prompt, array( 'max_tokens' => 100, 'temperature' => 0.9 ) );
	}

	/**
	 * Gerar conteúdo de email com IA
	 *
	 * @param string $context Contexto do email.
	 * @param string $type Tipo de email.
	 * @param array  $variables Variáveis disponíveis.
	 * @return string|WP_Error
	 */
	public function generate_email_content( $context, $type = 'marketing', $variables = array() ) {
		$system_prompt = "Você é um especialista em email marketing para e-commerce brasileiro.
Sua tarefa é criar emails HTML PROFISSIONAIS usando APENAS os componentes do nosso editor drag & drop.

🎨 ESTRUTURA OBRIGATÓRIA - Use TABELAS para layout:
- Layout base: <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\">
- Sempre use font-family: Arial, sans-serif
- TODOS os estilos devem ser INLINE (style='...')
- NÃO use <div> para layout principal, USE TABLES
- NÃO inclua <html>, <head>, <body> - apenas conteúdo interno

📦 COMPONENTES DISPONÍVEIS (escolha e combine):

1. CABEÇALHO COM LOGO:
<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"background-color: #ffffff;\">
<tr><td align=\"center\" style=\"padding: 30px 20px;\">
<img src=\"https://via.placeholder.com/200x60/667eea/ffffff?text=SUA+LOGO\" alt=\"Logo\" style=\"max-width: 200px;\">
</td></tr></table>

2. BANNER HERO (gradiente):
<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);\">
<tr><td align=\"center\" style=\"padding: 50px 30px;\">
<h1 style=\"color: #ffffff; font-size: 32px; font-weight: bold; margin: 0 0 15px 0; font-family: Arial, sans-serif;\">🔥 Título Principal</h1>
<p style=\"color: rgba(255,255,255,0.9); font-size: 16px; margin: 0 0 20px 0; font-family: Arial, sans-serif;\">Subtítulo aqui</p>
<a href=\"#\" style=\"display: inline-block; background: #ffffff; color: #667eea; padding: 12px 30px; text-decoration: none; border-radius: 25px; font-weight: bold; font-family: Arial, sans-serif;\">Ver Mais</a>
</td></tr></table>

3. TEXTO SIMPLES:
<p style=\"font-family: Arial, sans-serif; font-size: 14px; color: #333333; line-height: 1.6; margin: 0 0 15px 0; padding: 10px;\">Seu texto aqui.</p>

4. TÍTULO H2:
<h2 style=\"font-family: Arial, sans-serif; font-size: 24px; font-weight: bold; color: #333333; margin: 0 0 15px 0; padding: 10px;\">Subtítulo</h2>

5. BOTÃO CTA:
<div style=\"text-align: center; padding: 20px;\">
<a href=\"#\" style=\"display: inline-block; background: #667eea; color: #ffffff; padding: 14px 35px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px; font-family: Arial, sans-serif;\">Clique Aqui</a>
</div>

6. CARD PRODUTO:
<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"background-color: #ffffff; border: 1px solid #e5e5e5; border-radius: 8px;\">
<tr><td align=\"center\" style=\"padding: 15px;\">
<img src=\"https://via.placeholder.com/200x200/f5f5f5/999999?text=PRODUTO\" alt=\"Produto\" style=\"max-width: 200px;\">
<h3 style=\"color: #333; font-size: 16px; margin: 15px 0 5px 0; font-family: Arial, sans-serif;\">{{product_name}}</h3>
<p style=\"color: #e74c3c; font-size: 20px; font-weight: bold; margin: 5px 0 15px 0; font-family: Arial, sans-serif;\">{{product_price}}</p>
<a href=\"#\" style=\"display: inline-block; background: #27ae60; color: #ffffff; padding: 10px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; font-family: Arial, sans-serif; font-size: 14px;\">Comprar</a>
</td></tr></table>

7. CASHBACK (verde):
<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); border-radius: 10px;\">
<tr><td align=\"center\" style=\"padding: 30px;\">
<h2 style=\"color: #ffffff; font-size: 24px; font-weight: bold; margin: 0 0 10px 0; font-family: Arial, sans-serif;\">💰 Você tem {{cashback_balance}} de Cashback!</h2>
<p style=\"color: rgba(255,255,255,0.9); font-size: 14px; margin: 0 0 15px 0; font-family: Arial, sans-serif;\">Use na próxima compra</p>
<a href=\"#\" style=\"display: inline-block; background: #ffffff; color: #11998e; padding: 12px 30px; text-decoration: none; border-radius: 25px; font-weight: bold; font-family: Arial, sans-serif;\">Usar Cashback</a>
</td></tr></table>

8. CUPOM (amarelo):
<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"background-color: #fff3cd; border: 2px dashed #ffc107; border-radius: 10px;\">
<tr><td align=\"center\" style=\"padding: 25px;\">
<p style=\"color: #856404; font-size: 14px; margin: 0 0 8px 0; font-family: Arial, sans-serif;\">🎟️ Use o cupom:</p>
<h2 style=\"color: #856404; font-size: 28px; font-weight: bold; margin: 0 0 8px 0; font-family: Arial, sans-serif; letter-spacing: 3px;\">DESCONTO20</h2>
<p style=\"color: #856404; font-size: 14px; margin: 0; font-family: Arial, sans-serif;\">e ganhe 20% OFF!</p>
</td></tr></table>

9. RODAPÉ:
<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"background-color: #2c3e50;\">
<tr><td align=\"center\" style=\"padding: 30px;\">
<p style=\"color: #95a5a6; font-size: 13px; margin: 0 0 10px 0; font-family: Arial, sans-serif;\">{{site_name}}</p>
<p style=\"color: #7f8c8d; font-size: 11px; margin: 15px 0 0 0; font-family: Arial, sans-serif;\">
<a href=\"{{unsubscribe_url}}\" style=\"color: #95a5a6;\">Cancelar inscrição</a>
</p>
</td></tr></table>

✅ CORES RECOMENDADAS:
- Roxo: #667eea / #764ba2
- Verde: #11998e / #38ef7d / #27ae60
- Vermelho: #e74c3c / #f5576c
- Azul: #3b82f6
- Laranja: #f59e0b / #ffc107

🎯 VARIÁVEIS DISPONÍVEIS:
{{customer_name}}, {{customer_first_name}}, {{customer_email}}, {{product_name}}, {{product_image}}, {{product_price}}, {{cashback_balance}}, {{user_level}}, {{site_name}}, {{site_url}}

📋 ESTRUTURA RECOMENDADA:
1. Cabeçalho com logo
2. Banner hero OU título H2
3. 1-2 parágrafos de texto
4. Card especial (produto/cashback/cupom)
5. Botão CTA
6. Rodapé

⚠️ REGRAS CRÍTICAS:
- Monte o email COMBINANDO os componentes acima
- NÃO invente novos estilos, USE os componentes fornecidos
- Adapte os textos mas MANTENHA a estrutura HTML
- Use emojis nos títulos (💰 🎉 ⭐ 🔥 🎁 etc)
- Seja persuasivo e conversacional";

		$prompt = "Crie um email HTML PROFISSIONAL para: {$type}

Contexto: {$context}

IMPORTANTE: 
- Use APENAS os componentes fornecidos no system prompt
- Combine 4-6 componentes diferentes
- Mantenha a estrutura HTML exata dos componentes
- Adapte apenas os textos e escolha cores apropriadas
- O email deve ser editável no editor drag & drop

Retorne APENAS o HTML, sem explicações.";

		return $this->generate_text( $prompt, $system_prompt, array( 'max_tokens' => 2500, 'temperature' => 0.7 ) );
	}

	/**
	 * Testar conexão com a API
	 *
	 * @return array
	 */
	public function test_connection() {
		$result = $this->generate_text( 'Diga "Conexão OK" em português.', '', array( 'max_tokens' => 20 ) );

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'message' => $result,
			'model'   => $this->model,
		);
	}

	/**
	 * Gerar mensagem WhatsApp com IA
	 *
	 * @param array  $context Contexto da automação
	 * @param string $trigger Tipo de trigger
	 * @return string|WP_Error
	 */
	public function generate_whatsapp_message( $context, $trigger ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'no_api_key', __( 'OpenAI não configurada', 'person-cash-wallet' ) );
		}

		$system_prompt = "Você é um especialista em marketing conversacional e WhatsApp Business para e-commerce brasileiro.

Sua tarefa é criar mensagens CURTAS e OBJETIVAS para WhatsApp que sejam:
- Diretas e amigáveis (máximo 3-4 linhas)
- Com emojis relevantes (mas sem exagero, máximo 2-3 emojis)
- Personalizadas usando variáveis disponíveis
- Com call-to-action claro quando apropriado
- Tom conversacional, como se fosse uma pessoa real

IMPORTANTE:
- WhatsApp é informal, seja humano e próximo
- NUNCA ultrapasse 4 linhas
- Use quebras de linha para facilitar leitura
- Sempre use as variáveis fornecidas (ex: {{customer_first_name}})
- Termine com algo que incentive ação ou resposta

VARIÁVEIS DISPONÍVEIS: " . implode( ', ', $context['variables'] ) . "
Sempre que possível, use essas variáveis na mensagem.";

		$user_prompt = "Crie uma mensagem WhatsApp para:

Tipo de Automação: {$context['type']}
Objetivo: {$context['purpose']}
Trigger: {$trigger}
Negócio: {$context['site_name']}

Contexto adicional:
- Plataforma: WhatsApp Business
- Público: Clientes brasileiros de e-commerce
- Tom: Amigável, pessoal, conversacional

Crie a mensagem agora (apenas o texto, sem explicações):";

		$result = $this->generate_text( $user_prompt, $system_prompt, array(
			'temperature'   => 0.8,
			'max_tokens'    => 200,
			'top_p'         => 0.9,
			'presence_penalty' => 0.3,
		) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Limpar a mensagem
		$message = trim( $result );
		
		// Remover aspas que a IA às vezes adiciona
		$message = trim( $message, '"' );
		
		return $message;
	}

	/**
	 * Gerar variação única de mensagem WhatsApp para um destinatário específico.
	 *
	 * Usa a mensagem base como contexto e gera uma nova versão personalizada
	 * com pequenas variações de tom, ordem e expressões para evitar bloqueios
	 * por conteúdo repetitivo.
	 *
	 * @param string $base_message Mensagem base/template configurada na automação.
	 * @param array  $recipient_data Dados do destinatário (nome, etc.).
	 * @param string $trigger Tipo de trigger da automação.
	 * @return string|WP_Error
	 */
	public function generate_unique_whatsapp_variation( $base_message, $recipient_data = array(), $trigger = '' ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'no_api_key', __( 'OpenAI não configurada', 'person-cash-wallet' ) );
		}

		$recipient_name = isset( $recipient_data['first_name'] ) ? $recipient_data['first_name'] : 'cliente';
		$site_name      = get_bloginfo( 'name' );

		$system_prompt = "Você é um especialista em marketing conversacional para WhatsApp Business no Brasil.

Sua tarefa é REESCREVER uma mensagem de WhatsApp mantendo:
- O MESMO significado e objetivo da mensagem original
- As MESMAS variáveis entre chaves duplas ({{customer_first_name}}, etc.) INTACTAS
- Tom amigável e conversacional
- Máximo 4 linhas
- 2-3 emojis relevantes (podem ser diferentes dos originais)

VARIAÇÕES PERMITIDAS:
- Mudar a ordem das frases
- Usar sinônimos e expressões diferentes
- Ajustar o tom (mais animado, mais suave, mais direto)
- Adicionar ou remover uma interjeição no início
- Reformular o call-to-action

PROIBIDO:
- Remover ou alterar variáveis {{...}}
- Mudar o objetivo da mensagem
- Ultrapassar 4 linhas
- Adicionar informações que não estão na original";

		$user_prompt = "Reescreva esta mensagem para um cliente chamado '{$recipient_name}' da loja '{$site_name}':

MENSAGEM ORIGINAL:
{$base_message}

Gere APENAS a mensagem reescrita (sem explicações, sem aspas):";

		$result = $this->generate_text( $user_prompt, $system_prompt, array(
			'temperature'      => 0.9,
			'max_tokens'       => 250,
			'top_p'            => 0.95,
			'presence_penalty' => 0.5,
			'frequency_penalty' => 0.4,
		) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$message = trim( $result );
		$message = trim( $message, '"' );

		return $message;
	}
}
