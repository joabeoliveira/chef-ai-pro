<?php
namespace ChefAIPro;

/**
 * Classe Generator
 * Lida com a lógica de geração de conteúdo, chamadas de API e criação de posts.
 */
class Generator {

    public function __construct() {
        add_action('wp_ajax_chef_ai_pro_generate_recipe', [$this, 'handle_generate_recipe']);
        add_action('wp_footer', [$this, 'inject_schema_in_footer']);
    }

    /**
     * Manipula a requisição AJAX para gerar uma receita.
     */
    public function handle_generate_recipe() {
        check_ajax_referer('chef_ai_pro_generate_nonce', 'nonce');

        if (!current_user_can('publish_posts')) {
            wp_send_json_error(['message' => __('Você não tem permissão para publicar posts.', 'chef-ai-pro')], 403);
        }

        // Pega as chaves de API salvas
        $api_options = get_option('chef_ai_pro_api_settings');
        $llm_api_key = isset($api_options['llm_api_key']) ? $api_options['llm_api_key'] : '';

        if (empty($llm_api_key)) {
            wp_send_json_error(['message' => __('A chave de API do LLM não está configurada. Por favor, adicione-a na página de Configurações.', 'chef-ai-pro')]);
            return;
        }

        // Sanitiza os dados do POST
        $keyword = sanitize_text_field($_POST['keyword']);
        $author = sanitize_text_field($_POST['author_signature']);
        $link1 = esc_url_raw($_POST['internal_link_1']);
        $link2 = esc_url_raw($_POST['internal_link_2']);

        if (empty($keyword) || empty($author)) {
            wp_send_json_error(['message' => __('Palavra-chave e assinatura do autor são obrigatórios.', 'chef-ai-pro')]);
        }

        // --- CHAMADA REAL PARA A API DO LLM ---
        $api_response = $this->call_llm_api($keyword, $author, $llm_api_key);

        if (is_wp_error($api_response)) {
            wp_send_json_error(['message' => $api_response->get_error_message()]);
            return;
        }
        
        // Processa a resposta
        $post_data = $this->process_api_response($api_response, $keyword, $author, $link1, $link2);

        if (is_wp_error($post_data)) {
            wp_send_json_error(['message' => $post_data->get_error_message()]);
        }

        // Insere o post
        $post_id = wp_insert_post($post_data['post_args'], true);

        if (is_wp_error($post_id)) {
            wp_send_json_error(['message' => $post_id->get_error_message()]);
        }

        // Adiciona os metadados
        update_post_meta($post_id, '_is_chef_ai_pro_recipe', true); // Marca o post como gerado pelo plugin
        wp_set_post_tags($post_id, $post_data['tags']);
        update_post_meta($post_id, '_yoast_wpseo_title', $post_data['meta_title']); // Exemplo para Yoast
        update_post_meta($post_id, '_yoast_wpseo_metadesc', $post_data['meta_description']);
        update_post_meta($post_id, '_chef_ai_pro_schema_recipe', $post_data['schema_recipe']);
        update_post_meta($post_id, '_chef_ai_pro_schema_faq', $post_data['schema_faq']);
        update_post_meta($post_id, '_chef_ai_pro_schema_video', $post_data['schema_video']);
        
        $edit_link = get_edit_post_link($post_id, 'raw');

        wp_send_json_success([
            'message' => __('Receita gerada com sucesso como rascunho!', 'chef-ai-pro'),
            'edit_link' => $edit_link
        ]);
    }

    /**
     * Processa a resposta da API, separando conteúdo, metas e schemas.
     */
    private function process_api_response($response, $keyword, $author, $link1, $link2) {
        // Separa as partes da resposta usando os marcadores
        $parts = explode('<!--|||-->', $response);
        if (count($parts) < 7) {
            return new \WP_Error('api_error', 'Resposta da API em formato inválido.');
        }

        $content = trim($parts[0]);
        $meta_title = trim($parts[1]);
        $meta_description = trim($parts[2]);
        $tags = array_map('trim', explode(',', trim($parts[3])));
        $schema_recipe = trim($parts[4]);
        $schema_faq = trim($parts[5]);
        $schema_video = trim($parts[6]);
        
        // Substitui os placeholders de links internos
        if (!empty($link1)) {
            $content = preg_replace('/\[ANCHOR_LINK_1:(.*?)\]/i', '<a href="' . esc_url($link1) . '">$1</a>', $content);
        }
         if (!empty($link2)) {
            $content = preg_replace('/\[ANCHOR_LINK_2:(.*?)\]/i', '<a href="' . esc_url($link2) . '">$1</a>', $content);
        }

        return [
            'post_args' => [
                'post_title'   => $keyword,
                'post_content' => wp_kses_post($content),
                'post_status'  => 'draft',
                'post_author'  => get_current_user_id(),
                'post_type'    => 'post',
            ],
            'meta_title' => sanitize_text_field($meta_title),
            'meta_description' => sanitize_textarea_field($meta_description),
            'tags' => array_map('sanitize_text_field', $tags),
            'schema_recipe' => $schema_recipe, // JSON, não precisa sanitizar aqui
            'schema_faq' => $schema_faq,
            'schema_video' => $schema_video
        ];
    }
    
     /**
     * Injeta os schemas JSON-LD no rodapé de posts individuais.
     */
    public function inject_schema_in_footer() {
        if (is_single()) {
            $post_id = get_the_ID();
            $schema_recipe = get_post_meta($post_id, '_chef_ai_pro_schema_recipe', true);
            $schema_faq = get_post_meta($post_id, '_chef_ai_pro_schema_faq', true);
            $schema_video = get_post_meta($post_id, '_chef_ai_pro_schema_video', true);

            if ($schema_recipe) {
                echo "\n" . $schema_recipe . "\n";
            }
            if ($schema_faq) {
                echo "\n" . $schema_faq . "\n";
            }
            if ($schema_video) {
                echo "\n" . $schema_video . "\n";
            }
        }
    }

    /**
     * Constrói o prompt e chama a API do LLM.
     *
     * @param string $keyword A palavra-chave principal.
     * @param string $author A assinatura do autor.
     * @param string $api_key A chave de API para autenticação.
     * @return string|\WP_Error A resposta da API ou um erro.
     */
    private function call_llm_api($keyword, $author, $api_key) {
        // !! IMPORTANTE !! Substitua esta URL pela URL da API do seu LLM
        $api_url = 'https://api.openai.com/v1/chat/completions'; // Exemplo para OpenAI GPT

        $prompt = "
        Você é um especialista em SEO para receitas e um chef de cozinha. Crie um post de blog completo e otimizado para a palavra-chave: '{$keyword}'.
        O autor da receita é '{$author}'.
        A resposta DEVE ser dividida em 7 partes, separadas EXATAMENTE por '<!--|||-->'. Siga a ordem e o formato estritamente.

        PARTE 1: Conteúdo do Post (em HTML)
        - Comece com uma introdução original e envolvente na voz do chef {$author}.
        - Adicione um subtítulo 'Vídeo da Receita' e um iframe do YouTube (use um vídeo relevante para '{$keyword}').
        - Crie seções 'Ingredientes' (lista não ordenada <ul>) e 'Modo de Preparo' (lista ordenada <ol>).
        - Inclua uma seção 'Dicas do Chef' com conselhos úteis.
        - Crie uma seção 'Perguntas Frequentes (FAQ)' com 2 perguntas e respostas.
        - Inclua DOIS placeholders para links internos no texto: '[ANCHOR_LINK_1:texto-âncora-sugerido]' e '[ANCHOR_LINK_2:outro-texto-âncora]'.

        <!--|||-->

        PARTE 2: Meta Título (máximo 60 caracteres)
        - Crie um meta título otimizado para SEO para '{$keyword}'.

        <!--|||-->

        PARTE 3: Meta Descrição (máximo 155 caracteres)
        - Crie uma meta descrição atrativa para cliques para '{$keyword}'.

        <!--|||-->

        PARTE 4: Tags (de 5 a 10, separadas por vírgula)
        - Liste as tags mais relevantes para a receita.

        <!--|||-->

        PARTE 5: Schema Recipe (JSON-LD)
        - Gere um JSON-LD Schema.org 'Recipe' completo, incluindo o campo 'author' com o nome '{$author}'.

        <!--|||-->

        PARTE 6: Schema FAQPage (JSON-LD)
        - Gere um JSON-LD Schema.org 'FAQPage' baseado nas perguntas da PARTE 1.

        <!--|||-->

        PARTE 7: Schema VideoObject (JSON-LD)
        - Gere um JSON-LD Schema.org 'VideoObject' correspondente ao vídeo da PARTE 1.
        ";

        $body = [
            'model' => 'gpt-3.5-turbo', // Exemplo: ajuste o modelo conforme sua API
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.7,
        ];

        $args = [
            'body'    => json_encode($body),
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'timeout' => 60, // Aumenta o tempo de espera
        ];

        $response = wp_remote_post($api_url, $args);

        if (is_wp_error($response)) {
            return new \WP_Error('api_error', __('Erro ao conectar com a API do LLM.', 'chef-ai-pro'));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : $response_body;
            return new \WP_Error('api_response_error', 'API Error ' . $response_code . ': ' . $error_message);
        }

        $data = json_decode($response_body, true);
        
        // O caminho pode variar dependendo da API
        if (isset($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }

        return new \WP_Error('api_format_error', __('Formato de resposta inesperado da API.', 'chef-ai-pro'));
    }
}

