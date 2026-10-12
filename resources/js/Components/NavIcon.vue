<script setup>
defineProps({
    name: { type: String, required: true },
});
</script>

<template>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
        <template v-if="name === 'home'">
            <path d="M3 10.5 12 3l9 7.5" />
            <path d="M5 9v10a1 1 0 0 0 1 1h3v-6h6v6h3a1 1 0 0 0 1-1V9" />
        </template>
        <template v-else-if="name === 'plus-circle'">
            <circle cx="12" cy="12" r="9" />
            <line x1="12" y1="8" x2="12" y2="16" />
            <line x1="8" y1="12" x2="16" y2="12" />
        </template>
        <template v-else-if="name === 'receipt'">
            <path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3z" />
            <line x1="9" y1="8" x2="15" y2="8" />
            <line x1="9" y1="12" x2="15" y2="12" />
        </template>
        <template v-else-if="name === 'clock'">
            <circle cx="12" cy="12" r="9" />
            <polyline points="12,7 12,12 16,14" />
        </template>
        <template v-else-if="name === 'chart-bar'">
            <line x1="4" y1="20" x2="20" y2="20" />
            <rect x="6" y="12" width="3" height="8" />
            <rect x="11" y="8" width="3" height="12" />
            <rect x="16" y="4" width="3" height="16" />
        </template>
        <template v-else-if="name === 'currency'">
            <circle cx="12" cy="12" r="9" />
            <path d="M9 9.5c0-1 1-1.5 2.2-1.5 1.4 0 2.3.7 2.3 1.7 0 2.3-4.5 1.6-4.5 4 0 1.1 1 1.8 2.3 1.8 1.2 0 2.2-.6 2.2-1.6" />
            <line x1="12" y1="6" x2="12" y2="18" />
        </template>
        <template v-else-if="name === 'sun'">
            <circle cx="12" cy="12" r="4.2" />
            <path d="M12 3v2.2M12 18.8V21M4.9 4.9l1.6 1.6M17.5 17.5l1.6 1.6M3 12h2.2M18.8 12H21M4.9 19.1l1.6-1.6M17.5 6.5l1.6-1.6" />
        </template>
        <template v-else-if="name === 'moon'">
            <path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a6.8 6.8 0 0 0 10.5 10.5Z" />
        </template>
        <template v-else-if="name === 'coins'">
            <ellipse cx="9" cy="8" rx="6" ry="3.2" />
            <path d="M3 8v8c0 1.8 2.7 3.2 6 3.2s6-1.4 6-3.2V8" />
            <path d="M3 12.7c0 1.8 2.7 3.2 6 3.2s6-1.4 6-3.2" />
            <path d="M14.2 6.3c2.6.3 4.5 1.5 4.5 3s-1.9 2.7-4.5 3" />
        </template>
        <template v-else-if="name === 'users'">
            <circle cx="9" cy="8" r="3" />
            <path d="M3 20c0-3 2.7-5 6-5s6 2 6 5" />
            <circle cx="17" cy="9" r="2.3" />
            <path d="M15.5 15.2c2.6.3 4.5 2 4.5 4.8" />
        </template>
        <template v-else-if="name === 'settings'">
            <circle cx="12" cy="12" r="3" />
            <path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.6 1.6 0 0 0-1-1.5 1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.6 1.6 0 0 0 1.5-1 1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1Z" />
        </template>
        <template v-else-if="name === 'logout'">
            <path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h3" />
            <polyline points="14,8 18,12 14,16" />
            <line x1="18" y1="12" x2="8" y2="12" />
        </template>
        <template v-else-if="name === 'menu'">
            <path d="M4 6h16M4 12h16M4 18h16" />
        </template>
        <template v-else-if="name === 'close'">
            <path d="M6 18L18 6M6 6l12 12" />
        </template>
        <template v-else-if="name === 'chevron-down'">
            <polyline points="6,8 12,14 18,8" />
        </template>
        <template v-else-if="name === 'chevron-right'">
            <polyline points="9,18 15,12 9,6" />
        </template>
        <template v-else-if="name === 'chevron-left'">
            <polyline points="15,18 9,12 15,6" />
        </template>
        <template v-else-if="name === 'collapse'">
            <rect x="3" y="4" width="18" height="16" rx="2" />
            <line x1="9" y1="4" x2="9" y2="20" />
            <polyline points="15,9 13,12 15,15" />
        </template>
        <template v-else-if="name === 'user'">
            <circle cx="12" cy="8" r="3.2" />
            <path d="M5 19c0-3.5 3-6 7-6s7 2.5 7 6" />
        </template>
        <template v-else-if="name === 'check'">
            <polyline points="5,13 10,18 19,7" />
        </template>
        <template v-else-if="name === 'mail'">
            <rect x="3" y="5" width="18" height="14" rx="2" />
            <polyline points="3,7 12,13 21,7" />
        </template>
        <template v-else-if="name === 'lock'">
            <rect x="5" y="11" width="14" height="9" rx="2" />
            <path d="M8 11V8a4 4 0 0 1 8 0v3" />
        </template>
        <template v-else-if="name === 'eye'">
            <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" />
            <circle cx="12" cy="12" r="2.6" />
        </template>
        <template v-else-if="name === 'eye-off'">
            <path d="M3 3l18 18" />
            <path d="M10.6 5.7A10.6 10.6 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a15 15 0 0 1-3 3.7M6.6 6.7A15.6 15.6 0 0 0 2.5 12S6 18.5 12 18.5a9.9 9.9 0 0 0 3.3-.6" />
            <path d="M9.9 9.9a2.6 2.6 0 0 0 3.7 3.7" />
        </template>
    </svg>
</template>
