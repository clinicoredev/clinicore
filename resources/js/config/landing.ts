export interface PricingPlan {
    id: string;
    name: string;
    badge?: string;
    description: string;
    monthlyPrice: number | null;
    annualMonthlyPrice: number | null;
    customPriceLabel?: string;
    priceNote: string;
    highlighted?: boolean;
    ctaLabel: string;
    ctaHref: string;
    features: string[];
}

export interface FaqItem {
    question: string;
    answer: string;
}

export const PRODUCT_CONFIG = {
    name: 'CliniCore',
    domain: 'clinicore.es',
    url: 'https://clinicore.es',
    email: 'clinicore.dev@gmail.com',
    /**
     * Cuando grabes el vídeo demo de 60 s, pega aquí la URL de YouTube/Vimeo/MP4
     * (por ejemplo: 'https://www.youtube.com/embed/XXXX' o '/videos/demo-60s.mp4')
     * y se renderizará automáticamente en la pestaña de Vídeo Demo del Hero.
     */
    demoVideoUrl: '' as string,
};

export const PRICING_PLANS: PricingPlan[] = [
    {
        id: 'acceso-gratuito',
        name: 'Acceso Servicio (Actual)',
        badge: 'Gratis ahora',
        description:
            'Empieza a organizar las guardias de tu unidad hoy mismo sin trámites administrativos ni tarjeta.',
        monthlyPrice: 0,
        annualMonthlyPrice: 0,
        priceNote: 'Gratuito durante la fase de lanzamiento',
        highlighted: true,
        ctaLabel: 'Solicitar acceso gratis',
        ctaHref:
            'mailto:clinicore.dev@gmail.com?subject=Solicitud%20de%20acceso%20gratis%20a%20CliniCore&body=Hola%2C%0A%0AQueremos%20probar%20CliniCore%20en%20nuestro%20servicio%3A%0A-%20Hospital%3A%20%0A-%20Servicio%20/%20Unidad%3A%20%0A-%20Responsable%20(Jefe%20o%20Residente%20Mayor)%3A%20%0A-%20N%C2%BA%20aproximado%20de%20profesionales%3A%20',
        features: [
            'Generador automático con simulación previa (Dry-Run)',
            'Reglas de salientes, distancia mínima y bloques de fin de semana',
            'Equidad anual (17h, 24h, festivos y días especiales)',
            'Módulos separados de Adjuntos y Residentes + Calendario Global',
            'Permutas entre compañeros, bolsa de vacantes y ausencias',
            'Exportación a PDF, Excel (RRHH/Nómina) y sincronización Google Calendar / .ics',
        ],
    },
    {
        id: 'plan-servicio',
        name: 'Plan Servicio Pro',
        badge: 'Próximamente · Suscripción',
        description:
            'Pensado para servicios hospitalarios que quieren soporte prioritario, exportación limpia sin marca y personalización.',
        monthlyPrice: 39,
        annualMonthlyPrice: 31,
        priceNote: 'por servicio / mes (facturación por unidad, usuarios ilimitados)',
        highlighted: false,
        ctaLabel: 'Solicitar acceso anticipado',
        ctaHref: `mailto:${PRODUCT_CONFIG.email}?subject=Consulta%20Plan%20Servicio%20Pro%20CliniCore`,
        features: [
            'Todo lo incluido en Acceso Servicio sin límites de plantilla',
            'Exportaciones PDF y Excel sin marca de agua',
            'Onboarding asistido y configuración inicial de reglas del servicio',
            'Soporte prioritario directo por email con el equipo de producto',
            'Histórico multi-año y auditoría completa de cambios',
            'Factura emitida al servicio, asociación o profesional responsable',
        ],
    },
];

export const FAQ_ITEMS: FaqItem[] = [
    {
        question: '¿Sirve solo para médicos o también para otros servicios hospitalarios?',
        answer:
            'Sirve para cualquier unidad o servicio hospitalario que trabaje con guardias o turnos rotatorios: Medicina (todas las especialidades), Farmacia Hospitalaria, Enfermería, Urgencias, UCI o Análisis Clínicos. Puedes configurar modalidades presenciales y localizadas, plantillas con o sin residentes y festivos propios de tu centro o comunidad autónoma.',
    },
    {
        question: '¿Hace falta que lo contrate o pague el hospital?',
        answer:
            'No. CliniCore está diseñado para que un jefe de servicio, adjunto coordinador o residente mayor pueda ponerlo en marcha en minutos sin pasar por meses de licitaciones de informática hospitalaria. Actualmente es gratuito y, cuando se active el plan comercial, podrá gestionarse directamente por servicio.',
    },
    {
        question: '¿Se puede exportar el cuadrante a PDF y Excel para enviarlo por WhatsApp o a Nóminas?',
        answer:
            'Sí. Con un clic generas un PDF apaisado listo para imprimir en el tablón o enviar por WhatsApp (con tabla de equidad, calendario visual y listado cronológico) y un Excel con dos hojas: el detalle completo de turnos y un resumen consolidado de horas (17h, 24h, festivos y especiales) preparado para RRHH/Nómina.',
    },
    {
        question: '¿Cómo protege CliniCore los datos del equipo?',
        answer:
            'Cada servicio hospitalario opera en un espacio aislado (arquitectura multi-tenant por centro y unidad), de modo que ningún otro servicio puede ver vuestros cuadrantes ni vuestro personal. Además, CliniCore no almacena ningún dato de pacientes ni historias clínicas, solo la planificación interna de turnos del equipo.',
    },
    {
        question: '¿Respeta salientes obligatorios, vacaciones, bajas y fines de semana agrupados?',
        answer:
            'Sí. El motor comprueba automáticamente los salientes post-guardia, la distancia mínima configurable entre guardias, vacaciones, bajas y rotaciones externas, así como bloques de fin de semana (V-S, S-D o V-S-D) y limitaciones individuales. Además, puedes simular el cuadrante antes de guardarlo y hacer ajustes manuales o permutas con alerta de conflictos en tiempo real.',
    },
];
