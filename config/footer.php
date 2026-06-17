            </div>
        </main>
    </div>
</div>

<!-- hover previews desactivados --><div class="contents" style="display:none">

    <!-- Tooltip flotante (se reutiliza para folios y equipos) -->
    <div x-show="visible" x-cloak
         :style="`top: ${posY}px; left: ${posX}px;`"
         class="fixed z-[200] bg-white rounded-xl shadow-2xl border border-zinc-200 p-3.5 w-72 pointer-events-none animate-fade-in"
         x-transition.opacity.duration.150ms>

        <template x-if="tipo === 'cargando'">
            <div class="flex items-center gap-2 py-2">
                <i data-lucide="loader-2" class="w-4 h-4 text-zinc-400 animate-spin"></i>
                <span class="text-xs text-zinc-500">Cargando…</span>
            </div>
        </template>

        <template x-if="tipo === 'error'">
            <div class="text-xs text-zinc-500 py-1">
                <i data-lucide="alert-circle" class="w-4 h-4 text-zinc-400 inline -mt-0.5"></i>
                <span x-text="mensajeError"></span>
            </div>
        </template>

        <!-- Preview de incidencia -->
        <template x-if="tipo === 'incidencia' && datos">
            <div class="space-y-2">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-mono text-[10px] font-bold text-zinc-500" x-text="datos.folio"></span>
                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded uppercase"
                          :style="`color: ${datos.severidad.color}; background-color: ${datos.severidad.color}15`"
                          x-text="datos.severidad.nombre"></span>
                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded uppercase"
                          :style="`color: ${datos.estado.color}; background-color: ${datos.estado.color}15`"
                          x-text="datos.estado.nombre"></span>
                    <template x-if="datos.archivada">
                        <span class="text-[10px] text-zinc-500">📦</span>
                    </template>
                </div>
                <div class="font-semibold text-sm text-zinc-900 leading-snug" x-text="datos.titulo"></div>
                <div class="flex flex-wrap gap-x-2.5 gap-y-1 text-[11px] text-zinc-600">
                    <span class="flex items-center gap-1">
                        <i data-lucide="map-pin" class="w-3 h-3"></i>
                        <span x-text="datos.sucursal"></span>
                    </span>
                    <template x-if="datos.categoria">
                        <span class="flex items-center gap-1">
                            <i data-lucide="tag" class="w-3 h-3"></i>
                            <span x-text="datos.categoria"></span>
                        </span>
                    </template>
                    <template x-if="datos.asignado">
                        <span class="flex items-center gap-1">
                            <i data-lucide="user-check" class="w-3 h-3"></i>
                            <span x-text="datos.asignado"></span>
                        </span>
                    </template>
                </div>
                <div class="text-[10px] text-zinc-400 pt-1 border-t border-zinc-100" x-text="datos.tiempo_str"></div>
            </div>
        </template>

        <!-- Preview de equipo -->
        <template x-if="tipo === 'equipo' && datos">
            <div class="space-y-2">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-mono text-[10px] font-bold text-zinc-500" x-text="datos.codigo"></span>
                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded uppercase"
                          :style="`color: ${datos.estado.color}; background-color: ${datos.estado.color}15`"
                          x-text="datos.estado.nombre"></span>
                    <template x-if="datos.incidencias_abiertas > 0">
                        <span class="text-[10px] font-bold text-bacal-700 bg-bacal-50 px-1.5 py-0.5 rounded">
                            ⚠ <span x-text="datos.incidencias_abiertas"></span> abierta(s)
                        </span>
                    </template>
                </div>
                <div class="font-semibold text-sm text-zinc-900 leading-snug" x-text="datos.nombre"></div>
                <template x-if="datos.marca || datos.modelo">
                    <div class="text-[11px] text-zinc-600" x-text="(datos.marca || '') + ' ' + (datos.modelo || '')"></div>
                </template>
                <div class="flex flex-wrap gap-x-2.5 gap-y-1 text-[11px] text-zinc-600">
                    <span class="flex items-center gap-1">
                        <i data-lucide="map-pin" class="w-3 h-3"></i>
                        <span x-text="datos.sucursal_nombre || datos.sucursal"></span>
                    </span>
                    <template x-if="datos.area">
                        <span class="flex items-center gap-1">
                            <i data-lucide="layers" class="w-3 h-3"></i>
                            <span x-text="datos.area"></span>
                        </span>
                    </template>
                    <template x-if="datos.ubicacion">
                        <span class="flex items-center gap-1">
                            <i data-lucide="navigation" class="w-3 h-3"></i>
                            <span x-text="datos.ubicacion"></span>
                        </span>
                    </template>
                </div>
                <div class="text-[10px] text-zinc-400 pt-1 border-t border-zinc-100">
                    <span x-text="datos.incidencias_totales"></span> incidencia(s) en total
                </div>
            </div>
        </template>
    </div>
</div>

<script>
function hoverPreviews() { return {}; } // desactivado
/* function hoverPreviews() {
    return {
        visible: false,
        tipo: '',
        datos: null,
        mensajeError: '',
        posX: 0,
        posY: 0,
        timer: null,
        cache: {},  // cache por clave (tipo:valor)

        init() {
            // Patrones para detectar folios y códigos de equipo dentro del DOM
            // Folio: INC-XXX-NNNN-NNNN (ej. INC-BAC-2026-0048)
            // Equipo: cualquier código de inventario (los detectamos por data-attribute o class)

            this.detectarYAnotar();

            // Re-detectar cuando Alpine renderice cosas nuevas
            document.addEventListener('alpine:initialized', () => this.detectarYAnotar());

            // Observador para contenido dinámico
            const obs = new MutationObserver(() => {
                clearTimeout(this._obsTimer);
                this._obsTimer = setTimeout(() => this.detectarYAnotar(), 300);
            });
            obs.observe(document.body, { childList: true, subtree: true });
        },

        detectarYAnotar() {
            // Auto-anotar links que apuntan a incidencia_ver.php / equipo_ver.php
            const linksInc = document.querySelectorAll('a[href*="incidencia_ver.php?id="]:not([data-preview-attached])');
            linksInc.forEach(el => {
                const m = el.href.match(/[?&]id=(\d+)/);
                if (m) {
                    el.dataset.previewAttached = '1';
                    el.dataset.previewTipo = 'incidencia';
                    el.dataset.previewId = m[1];
                    this.attachListeners(el);
                }
            });

            const linksEq = document.querySelectorAll('a[href*="equipo_ver.php?id="]:not([data-preview-attached])');
            linksEq.forEach(el => {
                const m = el.href.match(/[?&]id=(\d+)/);
                if (m) {
                    el.dataset.previewAttached = '1';
                    el.dataset.previewTipo = 'equipo';
                    el.dataset.previewId = m[1];
                    this.attachListeners(el);
                }
            });

            // También detectar elementos marcados manualmente con data-folio o data-equipo-codigo
            document.querySelectorAll('[data-folio]:not([data-preview-attached])').forEach(el => {
                el.dataset.previewAttached = '1';
                el.dataset.previewTipo = 'incidencia';
                el.dataset.previewFolio = el.dataset.folio;
                this.attachListeners(el);
            });
            document.querySelectorAll('[data-equipo-codigo]:not([data-preview-attached])').forEach(el => {
                el.dataset.previewAttached = '1';
                el.dataset.previewTipo = 'equipo';
                el.dataset.previewCodigo = el.dataset.equipoCodigo;
                this.attachListeners(el);
            });
        },

        attachListeners(el) {
            el.addEventListener('mouseenter', (e) => this.entrar(e, el));
            el.addEventListener('mouseleave', () => this.salir());
        },

        entrar(evento, el) {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.mostrar(evento, el), 400); // 400ms de espera
        },

        salir() {
            clearTimeout(this.timer);
            this.visible = false;
        },

        async mostrar(evento, el) {
            const rect = el.getBoundingClientRect();
            // Posición: debajo del elemento, alineado a la izquierda
            this.posX = Math.min(rect.left, window.innerWidth - 300);
            this.posY = rect.bottom + 6;
            // Si no cabe abajo, mostrarlo arriba
            if (this.posY + 200 > window.innerHeight) {
                this.posY = rect.top - 200;
            }

            this.tipo = el.dataset.previewTipo;
            const tipo = this.tipo;

            const claveCache = (el.dataset.previewId ? 'id:' + el.dataset.previewId
                              : el.dataset.previewFolio ? 'folio:' + el.dataset.previewFolio
                              : 'codigo:' + el.dataset.previewCodigo);
            const key = tipo + '|' + claveCache;

            if (this.cache[key]) {
                this.datos = this.cache[key];
                this.visible = true;
                return;
            }

            this.tipo = 'cargando';
            this.visible = true;

            try {
                let url;
                if (tipo === 'incidencia') {
                    if (el.dataset.previewId) {
                        url = '<?= url('api/preview_folio.php') ?>?id=' + el.dataset.previewId;
                    } else {
                        url = '<?= url('api/preview_folio.php') ?>?folio=' + encodeURIComponent(el.dataset.previewFolio);
                    }
                } else {
                    if (el.dataset.previewId) {
                        url = '<?= url('api/preview_equipo.php') ?>?id=' + el.dataset.previewId;
                    } else {
                        url = '<?= url('api/preview_equipo.php') ?>?codigo=' + encodeURIComponent(el.dataset.previewCodigo);
                    }
                }

                const resp = await fetch(url, { credentials: 'same-origin' });
                const data = await resp.json();

                if (data.ok) {
                    this.datos = data.incidencia || data.equipo;
                    this.tipo = tipo;
                    this.cache[key] = this.datos;
                } else {
                    this.tipo = 'error';
                    this.mensajeError = data.error || 'No se pudo cargar';
                }
                this.$nextTick(() => { if (window.lucide) lucide.createIcons(); });
            } catch (e) {
                this.tipo = 'error';
                this.mensajeError = 'Error de conexión';
            }
        },
    }
} */
</script>

<script>
    // Inicializar íconos de Lucide al cargar el DOM
    document.addEventListener('DOMContentLoaded', () => {
        if (window.lucide) lucide.createIcons();
    });

    // Reinicializar íconos cuando Alpine muestre/oculte elementos
    document.addEventListener('alpine:initialized', () => {
        if (window.lucide) lucide.createIcons();
    });

    // Por si lucide carga después que el DOMContentLoaded
    window.addEventListener('load', () => {
        if (window.lucide) lucide.createIcons();
    });
</script>

</body>
</html>
