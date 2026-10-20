/**
 * Leitor de código de barras: câmera (ZXing, carregado sob demanda), leitor
 * USB/Bluetooth que "digita" + Enter, ou digitação manual. A leitura só
 * preenche o campo; quem confirma a operação é sempre o usuário (ou o Enter
 * do leitor), pra quadros consecutivos da câmera nunca duplicarem nada.
 *
 * config.mensagens: textos traduzidos vindos do Blade.
 * config.limparAoConcluir: limpa o campo quando a ação devolve true.
 * config.focarAoConcluir: devolve o foco ao campo (próxima leitura no PDV).
 */
export default (config = {}) => ({
    open: false,
    starting: false,
    busy: false,
    code: '',
    error: '',
    anuncio: '',
    controls: null,
    stream: null,
    generation: 0,
    init() {
        this.onLeave = () => this.stop();
        this.onHidden = () => { if (document.hidden) this.stop(); };
        document.addEventListener('livewire:navigating', this.onLeave);
        document.addEventListener('visibilitychange', this.onHidden);
    },
    msg(chave, fallback = '') {
        return config.mensagens?.[chave] ?? fallback;
    },
    async start() {
        if (this.starting || this.open) return;
        this.error = '';
        this.anuncio = '';
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            this.error = this.msg('erroHttps');
            return;
        }
        this.open = true;
        this.starting = true;
        // O botão "Câmera" fica desabilitado: sem isso o foco cairia no <body>.
        this.$nextTick(() => this.$refs.fechar?.focus());
        const generation = ++this.generation;
        try {
            const { BrowserMultiFormatOneDReader } = await import('@zxing/browser');
            if (generation !== this.generation) return;
            const stream = await navigator.mediaDevices.getUserMedia({video: {facingMode: {ideal: 'environment'}}, audio: false});
            if (generation !== this.generation) {
                stream.getTracks().forEach(track => track.stop());
                return;
            }
            this.stream = stream;
            const reader = new BrowserMultiFormatOneDReader();
            const controls = await reader.decodeFromStream(stream, this.$refs.video, (result, error, controls) => {
                if (!result || generation !== this.generation) return;
                this.code = result.getText();
                controls.stop();
                this.stop();
                this.anuncio = this.msg('codigoLido', '').replace(':codigo', this.code);
                this.$nextTick(() => this.$refs.input?.focus());
            });
            if (generation !== this.generation) controls.stop();
            else this.controls = controls;
        } catch (error) {
            if (generation !== this.generation) return;
            this.stop();
            this.error = error.name === 'NotAllowedError' ? this.msg('erroPermissao') : this.msg('erroCamera');
            this.$nextTick(() => this.$refs.input?.focus());
        } finally {
            if (generation === this.generation) this.starting = false;
        }
    },
    stop() {
        ++this.generation;
        this.controls?.stop();
        this.controls = null;
        this.stream?.getTracks().forEach(track => track.stop());
        this.stream = null;
        this.open = false;
        this.starting = false;
    },
    fecharCamera() {
        this.stop();
        this.$nextTick(() => this.$refs.input?.focus());
    },
    escape(event) {
        // Só consome o Escape quando a câmera está aberta; fora disso ele
        // precisa chegar ao modal que contém o leitor (ex.: cadastro de produto).
        if (!this.open && !this.starting) return;
        event.stopPropagation();
        this.fecharCamera();
    },
    async submit(callback) {
        if (this.busy || !this.code.trim()) return;
        this.stop();
        this.busy = true;
        this.error = '';
        this.anuncio = '';
        try {
            const concluiu = await callback(this.code.trim());
            if (concluiu === true && config.limparAoConcluir) this.code = '';
            if (concluiu === true && config.focarAoConcluir) this.$nextTick(() => this.$refs.input?.focus());
        } catch {
            this.error = this.msg('erroOperacao');
        } finally {
            this.busy = false;
        }
    },
    destroy() {
        this.stop();
        document.removeEventListener('livewire:navigating', this.onLeave);
        document.removeEventListener('visibilitychange', this.onHidden);
    },
});
