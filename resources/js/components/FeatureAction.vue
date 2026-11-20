<script setup lang="ts">
import { Button, type ButtonVariants } from '@/components/ui/button';
import { useFeatures } from '@/composables/useFeatures';
import { Link } from '@inertiajs/vue3';
import { computed, useId } from 'vue';

defineOptions({ inheritAttrs: false });
const props = defineProps<{
    feature: string;
    href?: string;
    disabled?: boolean;
    variant?: ButtonVariants['variant'];
    size?: ButtonVariants['size'];
    type?: 'button' | 'submit' | 'reset';
}>();
const emit = defineEmits<{ click: [event: MouseEvent] }>();
const { isEnabled } = useFeatures();
const unavailable = computed(() => !isEnabled(props.feature));
const descriptionId = useId();
</script>

<template>
    <span v-if="unavailable || disabled" class="inline-flex flex-col items-start gap-1">
        <Button
            v-bind="$attrs"
            :type="type ?? 'button'"
            :variant="variant"
            :size="size"
            disabled
            :aria-describedby="unavailable ? descriptionId : undefined"
            ><slot
        /></Button>
        <span v-if="unavailable" :id="descriptionId" class="text-xs text-muted-foreground">Temporarily unavailable</span>
    </span>
    <Button v-else-if="href" v-bind="$attrs" :variant="variant" :size="size" as-child>
        <Link :href="href"><slot /></Link>
    </Button>
    <Button v-else v-bind="$attrs" :type="type ?? 'button'" :variant="variant" :size="size" @click="emit('click', $event)"><slot /></Button>
</template>
