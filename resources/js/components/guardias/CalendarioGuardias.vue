<script setup>
import { computed } from 'vue';

const props = defineProps(['guardias']);

// Transformamos los datos del backend al formato que entiende V-Calendar
const attributes = computed(() => {
    return props.guardias.map(g => ({
        key: g.id,
        highlight: g.es_finde ? 'orange' : 'green', // Verde entre semana, naranja finde
        popover: {
            label: `${g.facultativo} (${g.tipo_badge})`,
        },
        dates: new Date(g.fecha_formateada.split('/').reverse().join('-')),
    }));
});
</script>

<template>
    <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-4 shadow-xl">
        <VCalendar 
            :attributes="attributes"
            is-dark
            expanded
            transparent
            borderless
            class="!bg-transparent"
        />
    </div>
</template>