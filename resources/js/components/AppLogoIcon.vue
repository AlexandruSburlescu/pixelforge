<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!-- 3×3 pixel mark: one pixel lit, the one being forged. -->
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" :class="className" v-bind="$attrs">
        <g fill="currentColor">
            <rect x="2" y="2" width="5.5" height="5.5" rx="1" opacity=".35" />
            <rect x="9.25" y="2" width="5.5" height="5.5" rx="1" opacity=".55" />
            <rect x="16.5" y="2" width="5.5" height="5.5" rx="1" opacity=".35" />
            <rect x="2" y="9.25" width="5.5" height="5.5" rx="1" opacity=".55" />
            <rect x="9.25" y="9.25" width="5.5" height="5.5" rx="1" />
            <rect x="16.5" y="9.25" width="5.5" height="5.5" rx="1" opacity=".55" />
            <rect x="2" y="16.5" width="5.5" height="5.5" rx="1" opacity=".35" />
            <rect x="9.25" y="16.5" width="5.5" height="5.5" rx="1" opacity=".55" />
            <rect x="16.5" y="16.5" width="5.5" height="5.5" rx="1" opacity=".35" />
        </g>
    </svg>
</template>
