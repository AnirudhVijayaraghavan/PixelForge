<script setup lang="ts">
import { ImagePlus, Plus, X } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardContent, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { validReference } from '@/lib/generations';

const urls = defineModel<string[]>({ required: true });
defineProps<{ errors: Record<string, string | undefined> }>();

function updateUrl(index: number, value: string | number) {
    const updated = [...urls.value];
    updated[index] = String(value);
    urls.value = updated;
}
function removeUrl(index: number) {
    urls.value = urls.value.filter((_, position) => position !== index);
}
</script>
<template>
    <Card class="gap-4 shadow-none">
        <CardHeader>
            <CardTitle
                class="flex items-center justify-between gap-2 text-base"
            >
                <span class="flex items-center gap-2"
                    ><ImagePlus class="size-4 text-muted-foreground" />Reference
                    images</span
                >
                <span class="text-xs font-normal text-muted-foreground"
                    >Optional · {{ urls.length }}/4</span
                >
            </CardTitle>
        </CardHeader>
        <CardContent class="space-y-3">
            <p
                id="reference-help"
                class="text-sm leading-relaxed text-muted-foreground"
            >
                Add HTTP(S) image URLs. Each reference adds input image tokens;
                Pixelforge counts the URLs without downloading them.
            </p>
            <template v-for="(referenceUrl, refIndex) in urls" :key="refIndex">
                <div class="space-y-1">
                    <label :for="'reference-' + refIndex" class="sr-only"
                        >Reference image {{ refIndex + 1 }} URL</label
                    >
                    <div class="flex gap-2">
                        <Input
                            :id="'reference-' + refIndex"
                            :model-value="referenceUrl"
                            type="url"
                            placeholder="Paste an HTTP(S) image URL"
                            :aria-describedby="
                                'reference-help reference-error-' + refIndex
                            "
                            :aria-invalid="!validReference(referenceUrl)"
                            @update:model-value="updateUrl(refIndex, $event)"
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            :aria-label="
                                'Remove reference image ' + (refIndex + 1)
                            "
                            @click="removeUrl(refIndex)"
                            ><X
                        /></Button>
                    </div>
                    <InputError
                        :id="'reference-error-' + refIndex"
                        :message="
                            errors['reference_images.' + refIndex] ||
                            (!validReference(referenceUrl)
                                ? 'Enter a valid HTTP(S) URL or remove this field.'
                                : undefined)
                        "
                    />
                </div>
            </template>
            <InputError :message="errors.reference_images" />
            <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="urls.length >= 4"
                @click="urls = [...urls, '']"
                ><Plus />Add reference URL</Button
            >
        </CardContent>
    </Card>
</template>
