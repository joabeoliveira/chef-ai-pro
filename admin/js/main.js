document.addEventListener('DOMContentLoaded', function () {

    /**
     * Lógica para o Gráfico de Tendências do Dashboard
     */
    const trendsChartCanvas = document.getElementById('trendsChart');
    if (trendsChartCanvas) {
        const ctx = trendsChartCanvas.getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                // Dados de exemplo
                labels: ['Semana 1', 'Semana 2', 'Semana 3', 'Semana 4'],
                datasets: [{
                    label: 'Receitas Geradas',
                    data: [12, 19, 8, 25],
                    borderColor: 'rgb(0, 115, 170)',
                    backgroundColor: 'rgba(0, 115, 170, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    /**
     * Lógica para o Formulário de Geração de Receitas
     */
    const generatorForm = document.getElementById('chef-ai-generator-form');
    if (generatorForm) {
        const statusDiv = document.getElementById('generation-status');
        const resultDiv = document.getElementById('generation-result');
        const submitButton = document.getElementById('generate-recipe-btn');
        
        generatorForm.addEventListener('submit', function (event) {
            event.preventDefault();

            // Reseta o estado
            statusDiv.style.display = 'flex';
            resultDiv.innerHTML = '';
            submitButton.disabled = true;

            const formData = new FormData(generatorForm);
            formData.append('action', 'chef_ai_pro_generate_recipe');
            
            // Usamos os dados passados pelo wp_localize_script
            formData.append('nonce', chefAiProData.nonce);

            fetch(chefAiProData.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                statusDiv.style.display = 'none';
                submitButton.disabled = false;
                
                if (data.success) {
                    resultDiv.innerHTML = `<div class="success">
                        <p>${data.data.message}</p>
                        <p><a href="${data.data.edit_link}" class="button" target="_blank">Revisar e Publicar Receita</a></p>
                    </div>`;
                    generatorForm.reset();
                } else {
                     resultDiv.innerHTML = `<div class="error">
                        <p><strong>Erro:</strong> ${data.data.message || chefAiProData.i18n.error}</p>
                    </div>`;
                }
            })
            .catch(error => {
                console.error('Erro na requisição:', error);
                statusDiv.style.display = 'none';
                submitButton.disabled = false;
                resultDiv.innerHTML = `<div class="error"><p>${chefAiProData.i18n.error}</p></div>`;
            });
        });
    }
});
