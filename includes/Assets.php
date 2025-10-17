<?php
namespace ChefAIPro;

/**
 * Classe Assets
 * Gerencia o enfileiramento de scripts e estilos de forma condicional.
 */
class Assets {

    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Enfileira os scripts e estilos apenas nas páginas do plugin.
     *
     * @param string $hook O hook da página atual.
     */
    public function enqueue_assets($hook) {
        // Lista de páginas do nosso plugin
        $plugin_pages = [
            'toplevel_page_chef-ai-pro',
            'chef-ai-pro_page_chef-ai-pro-generator',
            'chef-ai-pro_page_chef-ai-pro-settings'
        ];

        // Se não for uma página do nosso plugin, não carrega nada
        if (!in_array($hook, $plugin_pages)) {
            return;
        }

        // Carrega o CSS principal
        wp_enqueue_style(
            'chef-ai-pro-style',
            CHEF_AI_PRO_URL . 'admin/css/style.css',
            [],
            CHEF_AI_PRO_VERSION
        );

        // Carrega o Chart.js APENAS no dashboard
        if ('toplevel_page_chef-ai-pro' === $hook) {
            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js',
                [],
                '4.4.0',
                true
            );
        }
        
        // Carrega o JavaScript principal
        wp_enqueue_script(
            'chef-ai-pro-main-js',
            CHEF_AI_PRO_URL . 'admin/js/main.js',
            [],
            CHEF_AI_PRO_VERSION,
            true
        );

        // Passa dados do PHP para o JavaScript de forma segura
        wp_localize_script('chef-ai-pro-main-js', 'chefAiProData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('chef_ai_pro_generate_nonce'),
            'i18n'     => [
                'generating' => __('Gerando sua receita...', 'chef-ai-pro'),
                'error' => __('Ocorreu um erro. Tente novamente.', 'chef-ai-pro'),
            ]
        ]);
    }
}
