<script setup lang="ts">
import { Layers } from '@lucide/vue';
import { ref } from 'vue';
const separated = ref(false);
</script>
<template>
    <figure class="study" :class="{ separated }">
        <div class="mount">
            <div class="stack">
                <div class="sheet underlay" aria-hidden="true"></div>
                <div class="sheet proof" aria-hidden="true"></div>
                <div class="sheet print">
                    <img
                        src="/art/registration-study.svg"
                        alt="An abstract knot built from overlapping copper and sea-green contour rings."
                        width="490"
                        height="474"
                        fetchpriority="high"
                    />
                </div>
            </div>
        </div>
        <figcaption>
            <span
                ><strong>Registration study</strong
                ><small>Original studio artwork</small></span
            ><button
                type="button"
                :aria-pressed="separated"
                @click="separated = !separated"
            >
                <Layers :size="15" /> Separate layers
            </button>
        </figcaption>
    </figure>
</template>
<style scoped>
.study {
    background: #192720;
    color: #eef2dc;
    border: 1px solid #465047;
    overflow: hidden;
    border-radius: 1rem;
    isolation: isolate;
    box-shadow: 0 20px 55px -35px #132b24;
}
.mount {
    aspect-ratio: 1.27;
    display: grid;
    place-items: center;
    perspective: 850px;
    background:
        repeating-linear-gradient(0deg, transparent 0 39px, #c6d5bf0c 40px),
        repeating-linear-gradient(90deg, transparent 0 39px, #c6d5bf0c 40px),
        radial-gradient(ellipse at 70% 20%, #4b5448, #27382e 60%, #1a251e);
}
.stack {
    position: relative;
    width: 62%;
    aspect-ratio: 490/474;
    transform-style: preserve-3d;
    transform: rotateX(17deg) rotateY(-13deg) rotateZ(-7deg);
}
.sheet {
    position: absolute;
    inset: 0;
    border: 1px solid #6a7970;
    border-radius: 2px;
}
.underlay {
    background: repeating-linear-gradient(
        115deg,
        #93b4a2 0 2px,
        #526d60 2px 4px
    );
    transform: translate(12%, 9%) rotate(5deg);
    box-shadow: 0 12px 25px #101c18;
}
.proof {
    background: #db8f67;
    transform: translate(6%, 5%) rotate(2deg);
}
.print {
    padding: 1.7%;
    background: #e1decb;
    transform: translateZ(5px);
    box-shadow: 0 3px 10px #0c151c55;
}
.print img {
    width: 100%;
    height: 100%;
    display: block;
}
.separated .underlay {
    transform: translate(21%, 15%) rotate(10deg);
}
.separated .proof {
    transform: translate(5%, 1%) rotate(1deg);
}
.separated .print {
    transform: translate(-10%, -6%) rotate(-3deg);
}
figcaption {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    padding: 0.75rem 1rem;
    border-top: 1px solid #546257;
}
figcaption strong,
figcaption small {
    display: block;
}
figcaption small {
    font-size: 0.72rem;
    color: #bec9bd;
}
button {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    min-height: 44px;
    padding: 0.3rem 0.8rem;
    border: 1px solid #748274;
    border-radius: 2rem;
    font-size: 0.8rem;
    color: #f0f1e6;
    cursor: pointer;
}
button[aria-pressed='true'] {
    background: #dae4ce;
    color: #24352d;
}
@media (prefers-reduced-motion: no-preference) {
    .sheet,
    .stack {
        transition: transform 0.45s cubic-bezier(0.2, 0.7, 0.2, 1);
    }
    @media (hover: hover) {
        .mount:hover .stack {
            transform: rotateX(11deg) rotateY(-6deg) rotateZ(-5deg);
        }
    }
}
</style>
