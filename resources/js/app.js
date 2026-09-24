/*
 * Alpine NÃO é iniciado aqui.
 *
 * O Livewire 4 (que entra pelo Filament) já traz o próprio Alpine e o inicia
 * sozinho. Quando este arquivo também importava e dava `Alpine.start()`, a
 * página ficava com duas instâncias rodando sobre o mesmo DOM — o console
 * avisava "Detected multiple instances of Alpine running", e cada `x-data`
 * podia ser inicializado duas vezes, com dois estados independentes para o
 * mesmo elemento.
 *
 * O `window.Alpine` continua existindo: quem publica é o Livewire. Por isso
 * todo layout carrega `@livewireScripts`, inclusive as telas que não têm
 * componente Livewire nenhum (perfil, dashboard) mas usam `x-data` no menu e
 * nos modais — sem isso elas ficariam sem Alpine algum.
 *
 * Para registrar plugin ou componente Alpine, use o evento do Livewire:
 *
 *     document.addEventListener('livewire:init', () => {
 *         Alpine.plugin(meuPlugin)
 *     })
 */
