<?php
namespace ChefAIPro;

/**
 * Classe Admin
 * Gerencia o menu, as páginas de administração e as configurações do plugin.
 */
class Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Adiciona o menu do plugin no painel de administração do WordPress.
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Chef-AI Pro', 'chef-ai-pro'),
            __('Chef-AI Pro', 'chef-ai-pro'),
            'manage_options',
            'chef-ai-pro',
            [$this, 'dashboard_page_html'],
            'dashicons-food',
            25
        );

        add_submenu_page(
            'chef-ai-pro',
            __('Gerador de Receitas', 'chef-ai-pro'),
            __('Gerador de Receitas', 'chef-ai-pro'),
            'manage_options',
            'chef-ai-pro-generator',
            [$this, 'generator_page_html']
        );

        add_submenu_page(
            'chef-ai-pro',
            __('Configurações', 'chef-ai-pro'),
            __('Configurações', 'chef-ai-pro'),
            'manage_options',
            'chef-ai-pro-settings',
            [$this, 'settings_page_html']
        );
    }

    /**
     * Registra as seções e campos de configurações usando a Settings API.
     */
    public function register_settings() {
        // Seção de API
        register_setting('chef_ai_pro_settings_api', 'chef_ai_pro_api_settings', [$this, 'sanitize_api_settings']);
        add_settings_section('chef_ai_pro_api_section', __('Configurações de API', 'chef-ai-pro'), null, 'chef-ai-pro-settings');

        add_settings_field('llm_api_key', __('Chave de API do LLM', 'chef-ai-pro'), [$this, 'render_text_field'], 'chef-ai-pro-settings', 'chef_ai_pro_api_section', ['id' => 'llm_api_key', 'type' => 'password']);
        add_settings_field('youtube_api_key', __('Chave de API do YouTube', 'chef-ai-pro'), [$this, 'render_text_field'], 'chef-ai-pro-settings', 'chef_ai_pro_api_section', ['id' => 'youtube_api_key', 'type' => 'password']);
        add_settings_field('image_api_key', __('Chave de API de Imagem', 'chef-ai-pro'), [$this, 'render_text_field'], 'chef-ai-pro-settings', 'chef_ai_pro_api_section', ['id' => 'image_api_key', 'type' => 'password']);

        // Seção de Aparência
        register_setting('chef_ai_pro_settings_frontend', 'chef_ai_pro_frontend_settings', [$this, 'sanitize_frontend_settings']);
        add_settings_section('chef_ai_pro_frontend_section', __('Aparência (Front-end)', 'chef-ai-pro'), null, 'chef-ai-pro-settings');
        add_settings_field('primary_color', __('Cor Primária', 'chef-ai-pro'), [$this, 'render_color_field'], 'chef-ai-pro-settings', 'chef_ai_pro_frontend_section', ['id' => 'primary_color']);
    }
    
    // Funções de Renderização de Campos
    public function render_text_field($args) {
        $option_name = 'chef_ai_pro_api_settings';
        $options = get_option($option_name);
        $value = isset($options[$args['id']]) ? $options[$args['id']] : '';
        $type = isset($args['type']) ? $args['type'] : 'text';
        echo "<input type='{$type}' id='{$args['id']}' name='{$option_name}[{$args['id']}]' value='{$value}' class='regular-text'>";
    }

    public function render_color_field($args) {
        $option_name = 'chef_ai_pro_frontend_settings';
        $options = get_option($option_name);
        $value = isset($options[$args['id']]) ? $options[$args['id']] : '#0073aa';
        echo "<input type='color' id='{$args['id']}' name='{$option_name}[{$args['id']}]' value='{$value}'>";
    }

    // Funções de Sanitização
    public function sanitize_api_settings($input) {
        $sanitized_input = [];
        if (isset($input['llm_api_key'])) {
            $sanitized_input['llm_api_key'] = sanitize_text_field($input['llm_api_key']);
        }
        if (isset($input['youtube_api_key'])) {
            $sanitized_input['youtube_api_key'] = sanitize_text_field($input['youtube_api_key']);
        }
        if (isset($input['image_api_key'])) {
            $sanitized_input['image_api_key'] = sanitize_text_field($input['image_api_key']);
        }
        return $sanitized_input;
    }
    
    public function sanitize_frontend_settings($input) {
        $sanitized_input = [];
        if (isset($input['primary_color'])) {
            $sanitized_input['primary_color'] = sanitize_hex_color($input['primary_color']);
        }
        return $sanitized_input;
    }


    /**
     * Renderiza o HTML da página do Dashboard.
     */
    public function dashboard_page_html() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Busca dados reais
        $total_posts = wp_count_posts('post');
        $published_count = $total_posts->publish;
        $draft_count = $total_posts->draft;

        $args = [
            'post_type' => 'post',
            'posts_per_page' => 5,
            'meta_key' => '_is_chef_ai_pro_recipe',
            'meta_value' => true
        ];
        $recent_recipes = new \WP_Query($args);

        ?>
        <div class="wrap chef-ai-pro-wrap">
            <h1><?php _e('Dashboard - Chef-AI Pro', 'chef-ai-pro'); ?></h1>
            <p><?php _e('Bem-vindo! Aqui estão as estatísticas de suas receitas geradas.', 'chef-ai-pro'); ?></p>

            <div class="chef-ai-cards">
                <div class="card">
                    <h2><?php _e('Total de Posts', 'chef-ai-pro'); ?></h2>
                    <p class="stat"><?php echo esc_html($published_count + $draft_count); ?></p>
                </div>
                <div class="card">
                    <h2><?php _e('Posts Publicados', 'chef-ai-pro'); ?></h2>
                    <p class="stat"><?php echo esc_html($published_count); ?></p>
                </div>
                <div class="card">
                    <h2><?php _e('Rascunhos Pendentes', 'chef-ai-pro'); ?></h2>
                    <p class="stat"><?php echo esc_html($draft_count); ?></p>
                </div>
            </div>

            <div class="chef-ai-chart-container">
                 <h2><?php _e('Tendência de Geração (Últimos 30 dias)', 'chef-ai-pro'); ?></h2>
                 <canvas id="trendsChart"></canvas>
            </div>

            <div class="chef-ai-table-container">
                 <h2><?php _e('Últimas Receitas Geradas pelo Chef-AI Pro', 'chef-ai-pro'); ?></h2>
                 <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Título da Receita', 'chef-ai-pro'); ?></th>
                            <th><?php _e('Status', 'chef-ai-pro'); ?></th>
                            <th><?php _e('Data de Geração', 'chef-ai-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_recipes->have_posts()) : ?>
                            <?php while ($recent_recipes->have_posts()) : $recent_recipes->the_post(); ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo get_edit_post_link(); ?>"><?php the_title(); ?></a>
                                    </td>
                                    <td><?php echo get_post_status(); ?></td>
                                    <td><?php echo get_the_date(); ?></td>
                                </tr>
                            <?php endwhile; ?>
                            <?php wp_reset_postdata(); ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3"><?php _e('Nenhuma receita gerada ainda.', 'chef-ai-pro'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                 </table>
            </div>

        </div>
        <?php
    }

    /**
     * Renderiza o HTML da página de Geração de Receitas.
     */
    public function generator_page_html() {
        if (!current_user_can('publish_posts')) {
            return;
        }
        ?>
        <div class="wrap chef-ai-pro-wrap">
            <h1><?php _e('Gerador de Receitas Automático', 'chef-ai-pro'); ?></h1>
            <p><?php _e('Preencha os campos abaixo para gerar uma nova receita otimizada para SEO.', 'chef-ai-pro'); ?></p>
            
            <form id="chef-ai-generator-form" class="chef-ai-form">
                <?php wp_nonce_field('chef_ai_pro_generate_nonce', 'chef_ai_pro_nonce'); ?>

                <div class="form-row">
                    <label for="keyword"><?php _e('Palavra-Chave Principal', 'chef-ai-pro'); ?></label>
                    <input type="text" id="keyword" name="keyword" class="regular-text" placeholder="Ex: Receita de bolo de cenoura" required>
                    <p class="description"><?php _e('A palavra-chave que será o foco da receita.', 'chef-ai-pro'); ?></p>
                </div>
                
                <div class="form-row">
                    <label for="author_signature"><?php _e('Assinatura do Autor/Chef', 'chef-ai-pro'); ?></label>
                    <input type="text" id="author_signature" name="author_signature" class="regular-text" placeholder="Ex: Chef renomado João Silva" required>
                    <p class="description"><?php _e('Este nome será usado no schema e no conteúdo para reforçar EEAT.', 'chef-ai-pro'); ?></p>
                </div>

                <div class="form-row form-flex">
                    <div class="form-group">
                        <label for="internal_link_1"><?php _e('URL do Link Interno 1', 'chef-ai-pro'); ?></label>
                         <input type="url" id="internal_link_1" name="internal_link_1" class="regular-text" placeholder="https://seusite.com/outra-receita">
                    </div>
                     <div class="form-group">
                        <label for="internal_link_2"><?php _e('URL do Link Interno 2', 'chef-ai-pro'); ?></label>
                         <input type="url" id="internal_link_2" name="internal_link_2" class="regular-text" placeholder="https://seusite.com/dicas-de-cozinha">
                    </div>
                </div>
                 <p class="description"><?php _e('O LLM irá gerar o texto âncora. Forneça os URLs para os links internos.', 'chef-ai-pro'); ?></p>

                <div class="form-row">
                    <button type="submit" id="generate-recipe-btn" class="button button-primary button-hero">
                        <span class="dashicons dashicons-admin-generic"></span> <?php _e('Gerar Receita Agora', 'chef-ai-pro'); ?>
                    </button>
                </div>
            </form>

            <div id="generation-status" class="generation-status" style="display:none;">
                <div class="spinner is-active"></div>
                <p><?php _e('Gerando sua receita... Este processo pode levar alguns instantes.', 'chef-ai-pro'); ?></p>
            </div>
             <div id="generation-result" class="generation-result"></div>
        </div>
        <?php
    }

    /**
     * Renderiza o HTML da página de Configurações.
     */
    public function settings_page_html() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap chef-ai-pro-wrap">
            <h1><?php _e('Configurações - Chef-AI Pro', 'chef-ai-pro'); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('chef_ai_pro_settings_api');
                settings_fields('chef_ai_pro_settings_frontend');
                do_settings_sections('chef-ai-pro-settings');
                submit_button(__('Salvar Configurações', 'chef-ai-pro'));
                ?>
            </form>
        </div>
        <?php
    }
}

