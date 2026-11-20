<script setup lang="ts">
import {
    CalendarDays,
    CheckCircle2,
    Download,
    Play,
    ShieldCheck,
    Sparkles,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { PRODUCT_CONFIG } from '@/config/landing';

const vistaActiva = ref<'cuadrante' | 'video'>('cuadrante');
const colectivoActivo = ref<'adjuntos' | 'residentes'>('adjuntos');
const simulacionActiva = ref(false);

interface DemoDay {
    dia: number;
    tipo: 'laborable' | 'finde' | 'festivo' | 'especial';
    etiqueta?: string;
    adjunto: string;
    residente: string;
    horas: string;
    modalidad?: string;
}

const diasDemo: DemoDay[] = [
    { dia: 1, tipo: 'laborable', adjunto: 'M. García', residente: 'R3 López', horas: '17h' },
    { dia: 2, tipo: 'laborable', adjunto: 'C. Navarro', residente: 'R2 Ruiz', horas: '17h' },
    { dia: 3, tipo: 'laborable', adjunto: 'E. Serrano', residente: 'R4 Martín', horas: '17h', modalidad: 'Loc.' },
    { dia: 4, tipo: 'laborable', adjunto: 'J. Morales', residente: 'R1 Díaz', horas: '17h' },
    { dia: 5, tipo: 'laborable', adjunto: 'A. Torres', residente: 'R3 López', horas: '17h' },
    { dia: 6, tipo: 'especial', etiqueta: 'Festivo Especial', adjunto: 'L. Romero', residente: 'R4 Martín', horas: '24h' },
    { dia: 7, tipo: 'finde', adjunto: 'L. Romero', residente: 'R4 Martín', horas: '24h' },
    { dia: 8, tipo: 'laborable', adjunto: 'P. Jiménez', residente: 'R2 Ruiz', horas: '17h' },
    { dia: 9, tipo: 'laborable', adjunto: 'M. García', residente: 'R1 Díaz', horas: '17h' },
    { dia: 10, tipo: 'laborable', adjunto: 'C. Navarro', residente: 'R3 López', horas: '17h' },
    { dia: 11, tipo: 'laborable', adjunto: 'E. Serrano', residente: 'R2 Ruiz', horas: '17h', modalidad: 'Loc.' },
    { dia: 12, tipo: 'laborable', adjunto: 'J. Morales', residente: 'R4 Martín', horas: '17h' },
    { dia: 13, tipo: 'finde', adjunto: 'A. Torres', residente: 'R1 Díaz', horas: '24h' },
    { dia: 14, tipo: 'finde', adjunto: 'A. Torres', residente: 'R1 Díaz', horas: '24h' },
];

const resumenEquidad = computed(() =>
    colectivoActivo.value === 'adjuntos'
        ? [
              { nombre: 'M. García', h17: 3, h24: 1, totalHoras: 75, pct: 86 },
              { nombre: 'C. Navarro', h17: 3, h24: 1, totalHoras: 75, pct: 86 },
              { nombre: 'L. Romero', h17: 2, h24: 2, totalHoras: 82, pct: 92 },
              { nombre: 'A. Torres', h17: 2, h24: 2, totalHoras: 82, pct: 92 },
          ]
        : [
              { nombre: 'R4 Martín', h17: 2, h24: 2, totalHoras: 82, pct: 91 },
              { nombre: 'R3 López', h17: 3, h24: 1, totalHoras: 75, pct: 85 },
              { nombre: 'R2 Ruiz', h17: 3, h24: 1, totalHoras: 75, pct: 85 },
              { nombre: 'R1 Díaz', h17: 2, h24: 2, totalHoras: 82, pct: 91 },
          ],
);

const ejecutarSimulacion = () => {
    simulacionActiva.value = true;
    setTimeout(() => {
        simulacionActiva.value = false;
    }, 1800);
};
</script>

<template>
    <div id="demo" class="relative mx-auto w-full max-w-5xl">
        <!-- Halo sutil -->
        <div
            class="pointer-events-none absolute -inset-4 rounded-3xl bg-gradient-to-tr from-emerald-500/15 via-teal-500/5 to-transparent blur-2xl"
        ></div>

        <div
            class="relative overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-900/95 shadow-2xl shadow-black/60 backdrop-blur"
        >
            <!-- Barra superior estilo app real -->
            <div
                class="flex flex-col gap-3 border-b border-zinc-800 bg-zinc-950/90 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5"
            >
                <div class="flex items-center gap-2.5">
                    <div class="flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-full bg-zinc-700"></span>
                        <span class="h-2.5 w-2.5 rounded-full bg-zinc-700"></span>
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-500/80"></span>
                    </div>
                    <span class="font-mono text-xs text-zinc-400">
                        clinicore.es/guardias · Servicio de Farmacia Hospitalaria / Medicina Interna
                    </span>
                </div>

                <!-- Selector Vista Interactiva / Vídeo 60s -->
                <div
                    class="inline-flex self-start rounded-lg border border-zinc-800 bg-zinc-900 p-1 text-xs sm:self-auto"
                >
                    <button
                        type="button"
                        @click="vistaActiva = 'cuadrante'"
                        class="flex cursor-pointer items-center gap-1.5 rounded-md px-3 py-1.5 font-semibold transition-all"
                        :class="
                            vistaActiva === 'cuadrante'
                                ? 'bg-emerald-500 text-zinc-950 shadow-sm'
                                : 'text-zinc-400 hover:text-zinc-200'
                        "
                    >
                        <CalendarDays class="h-3.5 w-3.5" />
                        Vista previa interactiva
                    </button>
                    <button
                        type="button"
                        @click="vistaActiva = 'video'"
                        class="flex cursor-pointer items-center gap-1.5 rounded-md px-3 py-1.5 font-semibold transition-all"
                        :class="
                            vistaActiva === 'video'
                                ? 'bg-emerald-500 text-zinc-950 shadow-sm'
                                : 'text-zinc-400 hover:text-zinc-200'
                        "
                    >
                        <Play class="h-3.5 w-3.5" />
                        Vídeo demo (60 s)
                    </button>
                </div>
            </div>

            <!-- VISTA 1: MOCKUP INTERACTIVO FIEL A LA APP -->
            <div v-if="vistaActiva === 'cuadrante'" class="p-4 sm:p-6">
                <!-- Cabecera de controles reales -->
                <div
                    class="mb-5 flex flex-col justify-between gap-3 rounded-xl border border-zinc-800/90 bg-zinc-950/70 p-3.5 sm:flex-row sm:items-center"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            @click="colectivoActivo = 'adjuntos'"
                            class="cursor-pointer rounded-lg border px-3 py-1.5 text-xs font-bold transition-all"
                            :class="
                                colectivoActivo === 'adjuntos'
                                    ? 'border-emerald-500/40 bg-emerald-500/15 text-emerald-300'
                                    : 'border-zinc-800 bg-zinc-900 text-zinc-400 hover:text-zinc-200'
                            "
                        >
                            Adjuntos
                        </button>
                        <button
                            type="button"
                            @click="colectivoActivo = 'residentes'"
                            class="cursor-pointer rounded-lg border px-3 py-1.5 text-xs font-bold transition-all"
                            :class="
                                colectivoActivo === 'residentes'
                                    ? 'border-purple-500/40 bg-purple-500/15 text-purple-300'
                                    : 'border-zinc-800 bg-zinc-900 text-zinc-400 hover:text-zinc-200'
                            "
                        >
                            Residentes (MIR / FIR / EIR)
                        </button>
                        <span
                            class="hidden items-center gap-1 rounded-full border border-zinc-800 bg-zinc-900 px-2.5 py-1 font-mono text-[11px] text-zinc-400 md:inline-flex"
                        >
                            <ShieldCheck class="h-3.5 w-3.5 text-emerald-400" />
                            0 conflictos · Salientes OK · Bloque S-D
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="ejecutarSimulacion"
                            class="flex cursor-pointer items-center gap-1.5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3 py-1.5 text-xs font-bold text-emerald-300 transition-colors hover:bg-emerald-500/20"
                        >
                            <Sparkles class="h-3.5 w-3.5" />
                            {{ simulacionActiva ? 'Validando reglas...' : 'Simular (Dry-Run)' }}
                        </button>
                        <span
                            class="inline-flex items-center gap-1 rounded-lg border border-zinc-800 bg-zinc-900 px-2.5 py-1.5 text-[11px] font-semibold text-zinc-300"
                        >
                            <Download class="h-3.5 w-3.5 text-zinc-400" />
                            PDF / Excel
                        </span>
                    </div>
                </div>

                <!-- Grid calendario (2 semanas representativas) + Panel Equidad -->
                <div class="grid grid-cols-1 gap-5 lg:grid-cols-12">
                    <div class="lg:col-span-8">
                        <div class="mb-2 grid grid-cols-7 gap-1.5 text-center font-mono text-[10px] font-bold uppercase tracking-wider text-zinc-500">
                            <div>Lun</div>
                            <div>Mar</div>
                            <div>Mié</div>
                            <div>Jue</div>
                            <div>Vie</div>
                            <div class="text-amber-400/90">Sáb</div>
                            <div class="text-amber-400/90">Dom</div>
                        </div>

                        <div
                            class="grid grid-cols-7 gap-1.5 transition-opacity duration-300"
                            :class="simulacionActiva ? 'opacity-60' : 'opacity-100'"
                        >
                            <div
                                v-for="celda in diasDemo"
                                :key="celda.dia"
                                class="flex min-h-[74px] flex-col justify-between rounded-lg border p-1.5 text-left transition-all sm:min-h-[82px] sm:p-2"
                                :class="{
                                    'border-rose-500/40 bg-rose-950/25': celda.tipo === 'especial',
                                    'border-amber-500/30 bg-amber-950/20': celda.tipo === 'finde',
                                    'border-zinc-800/90 bg-zinc-950/70': celda.tipo === 'laborable',
                                }"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="font-mono text-[10px] font-bold text-zinc-400">{{ celda.dia }}</span>
                                    <span
                                        v-if="celda.tipo === 'especial'"
                                        class="rounded bg-rose-500/20 px-1 py-0.5 text-[8px] font-bold text-rose-300"
                                    >
                                        ESP
                                    </span>
                                    <span
                                        v-else-if="celda.tipo === 'finde'"
                                        class="text-[9px] font-semibold text-amber-400/90"
                                    >
                                        24h
                                    </span>
                                </div>

                                <div
                                    class="mt-1 truncate rounded px-1.5 py-1 text-[10px] font-bold sm:text-[11px]"
                                    :class="
                                        celda.tipo === 'especial'
                                            ? 'bg-rose-500/20 text-rose-200'
                                            : celda.tipo === 'finde'
                                              ? 'bg-amber-500/15 text-amber-200'
                                              : colectivoActivo === 'residentes'
                                                ? 'bg-purple-500/15 text-purple-200'
                                                : 'bg-emerald-500/15 text-emerald-200'
                                    "
                                >
                                    {{ colectivoActivo === 'adjuntos' ? celda.adjunto : celda.residente }}
                                </div>

                                <div class="mt-0.5 flex items-center justify-between font-mono text-[9px] text-zinc-500">
                                    <span>{{ celda.horas }}</span>
                                    <span v-if="celda.modalidad" class="text-sky-400">{{ celda.modalidad }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Resumen de equidad lateral -->
                    <div
                        class="flex flex-col justify-between rounded-xl border border-zinc-800 bg-zinc-950/80 p-4 lg:col-span-4"
                    >
                        <div>
                            <div class="mb-3 flex items-center justify-between">
                                <span class="flex items-center gap-1.5 text-xs font-bold text-white">
                                    <Users class="h-3.5 w-3.5 text-emerald-400" />
                                    Equidad mensual y YTD
                                </span>
                                <span class="font-mono text-[10px] text-emerald-400">Desviación &lt; 3%</span>
                            </div>

                            <div class="space-y-3">
                                <div v-for="fila in resumenEquidad" :key="fila.nombre" class="space-y-1">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="font-semibold text-zinc-200">{{ fila.nombre }}</span>
                                        <span class="font-mono text-[11px] text-zinc-400">
                                            {{ fila.h17 }}×17h · {{ fila.h24 }}×24h ·
                                            <strong class="text-white">{{ fila.totalHoras }}h</strong>
                                        </span>
                                    </div>
                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-800">
                                        <div
                                            class="h-full rounded-full transition-all duration-500"
                                            :class="
                                                colectivoActivo === 'residentes'
                                                    ? 'bg-purple-400'
                                                    : 'bg-emerald-400'
                                            "
                                            :style="{ width: `${fila.pct}%` }"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="mt-4 flex items-center gap-2 rounded-lg border border-emerald-500/20 bg-emerald-500/5 px-3 py-2 text-[11px] text-emerald-300"
                        >
                            <CheckCircle2 class="h-4 w-4 shrink-0 text-emerald-400" />
                            <span>Salientes, libranzas y festivos especiales validados automáticamente.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- VISTA 2: ESPACIO VÍDEO DEMO 60 S -->
            <div v-else class="p-6 sm:p-10">
                <div
                    v-if="PRODUCT_CONFIG.demoVideoUrl"
                    class="aspect-video w-full overflow-hidden rounded-xl border border-zinc-800 bg-black"
                >
                    <iframe
                        :src="PRODUCT_CONFIG.demoVideoUrl"
                        title="Demo CliniCore 60 segundos"
                        class="h-full w-full"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen
                    ></iframe>
                </div>

                <div
                    v-else
                    class="relative flex aspect-video w-full flex-col items-center justify-center overflow-hidden rounded-xl border border-zinc-800 bg-gradient-to-br from-zinc-950 via-zinc-900 to-emerald-950/30 p-6 text-center"
                >
                    <div
                        class="mb-4 flex h-16 w-16 items-center justify-center rounded-full border border-emerald-500/40 bg-emerald-500/15 text-emerald-400 shadow-lg shadow-emerald-500/15"
                    >
                        <Play class="ml-1 h-7 w-7 fill-emerald-400" />
                    </div>
                    <p class="text-base font-bold text-white sm:text-lg">
                        Demo en vídeo de 60 segundos
                    </p>
                    <p class="mt-1.5 max-w-md text-xs text-zinc-400 sm:text-sm">
                        Cómo generar el cuadrante del mes, revisar conflictos en tiempo real y exportar a PDF/Excel en menos de un minuto.
                    </p>
                    <div class="mt-5 flex flex-wrap items-center justify-center gap-2 font-mono text-[11px] text-zinc-400">
                        <span class="rounded-md border border-zinc-800 bg-zinc-950/80 px-2.5 py-1">00:00 Reglas y plantilla</span>
                        <span class="rounded-md border border-zinc-800 bg-zinc-950/80 px-2.5 py-1">00:20 Generación + Dry-Run</span>
                        <span class="rounded-md border border-zinc-800 bg-zinc-950/80 px-2.5 py-1">00:45 PDF, Excel y Calendar</span>
                    </div>
                    <button
                        type="button"
                        @click="vistaActiva = 'cuadrante'"
                        class="mt-5 cursor-pointer text-xs font-semibold text-emerald-400 underline underline-offset-4 hover:text-emerald-300"
                    >
                        Explorar la vista previa interactiva ahora
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
