<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, SlidersHorizontal, Layers, ScanLine } from '@lucide/vue';
import { ref } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import RegistrationStudy from '@/components/RegistrationStudy.vue';
import GenerationOptions from '@/components/generations/GenerationOptions.vue';
import { login, register } from '@/routes';
import type { GenerationSize, GenerationQuality } from '@/types/generation';
const size = ref<GenerationSize>('1536x1024');
const quality = ref<GenerationQuality>('medium');
const steps = [
    {
        title: 'Describe',
        icon: SlidersHorizontal,
        copy: 'Write a prompt, choose a format and quality, and add up to four reference URLs.',
    },
    {
        title: 'Generate',
        icon: Layers,
        copy: 'Review the estimate and credit hold. Submit once and follow the request from queued to complete.',
    },
    {
        title: 'Explore',
        icon: ScanLine,
        copy: 'Open your private history to inspect an image, reuse its prompt, or trace its exact charge in the Cost Inspector.',
    },
];
</script>
<template>
    <div class="press">
        <Head title="Pixelforge Studio — Image workspace"
            ><meta
                name="description"
                content="A private image workspace with live generation status, simulated credits and exact token-based pricing."
        /></Head>
        <a class="skip" href="#main">Skip to content</a>
        <header class="wrap mast">
            <a class="brand" href="#main"
                ><AppLogoIcon class="size-7" /> Pixelforge Studio</a
            >
            <nav aria-label="Page sections" class="anchors">
                <a href="#features">Features</a
                ><a href="#process">How it works</a
                ><a href="#pricing">Pricing</a>
            </nav>
            <nav aria-label="Account" class="account">
                <Link :href="login()">Log in</Link
                ><Link class="ink" :href="register()"
                    >Get started <ArrowRight :size="15"
                /></Link>
            </nav>
        </header>
        <main id="main">
            <section class="wrap hero">
                <div class="intro">
                    <p class="kicker">Image creation workspace</p>
                    <h1>Turn imagination<br />into a <em>canvas.</em></h1>
                    <p class="lede">
                        Shape an idea with a prompt, follow it through the
                        queue, and see exactly what each image costs.
                    </p>
                    <div class="actions">
                        <Link class="ink" :href="register()"
                            >Start creating <ArrowRight :size="17" /></Link
                        ><a href="#process"
                            >How it works <span aria-hidden="true">↓</span></a
                        >
                    </div>
                </div>
                <RegistrationStudy />
            </section>
            <section id="process" class="process">
                <div class="wrap">
                    <h2>From prompt to proof.</h2>
                    <div class="steps">
                        <article v-for="step in steps" :key="step.title">
                            <component
                                :is="step.icon"
                                aria-hidden="true"
                                :size="23"
                            />
                            <h3>{{ step.title }}</h3>
                            <p>{{ step.copy }}</p>
                        </article>
                    </div>
                </div>
            </section>
            <section id="features" class="wrap spread">
                <div>
                    <p class="kicker">Studio preview</p>
                    <h2>Choose the shape<br />of your image.</h2>
                    <p class="body">
                        Try the Studio controls. Your signed-in workspace adds a
                        prompt editor, live estimates and a private generation
                        history.
                    </p>
                    <p class="note">
                        Pixelforge produces simulated abstract images. The print
                        above is original interface artwork.
                    </p>
                </div>
                <div class="preview bg-card text-card-foreground">
                    <div class="preview-head">
                        <SlidersHorizontal :size="17" /> Composer preview
                    </div>
                    <GenerationOptions
                        v-model:size="size"
                        v-model:quality="quality"
                        :errors="{}"
                    />
                    <p class="mt-4 text-xs text-muted-foreground">
                        Preview only. These settings use no credits.
                    </p>
                </div>
            </section>
            <section id="pricing" class="pricing">
                <div class="wrap spread">
                    <div>
                        <p class="kicker">Transparent pricing</p>
                        <h2>Every credit,<br /><em>accounted for.</em></h2>
                        <p class="body">
                            Buy simulated USD credits in your wallet. No card or
                            real payment is involved.
                        </p>
                    </div>
                    <dl>
                        <div>
                            <dt>Before submission</dt>
                            <dd>
                                Studio quotes expected token usage plus a 25%
                                markup and shows the maximum reservation at the
                                quoted rates.
                            </dd>
                        </div>
                        <div>
                            <dt>While it runs</dt>
                            <dd>
                                The hold reduces available credits. If accepted
                                rates rise, the final charge can exceed the
                                original hold.
                            </dd>
                        </div>
                        <div>
                            <dt>At completion</dt>
                            <dd>
                                Success captures the exact usage-based charge
                                and releases unused credits. A confirmed failure
                                releases the hold and costs zero.
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>
            <section class="wrap closing">
                <div>
                    <h2>Start with a prompt.</h2>
                    <p class="body">
                        Create an account to open your private Studio and
                        wallet.
                    </p>
                </div>
                <Link class="ink" :href="register()"
                    >Open your Studio <ArrowRight :size="18"
                /></Link>
            </section>
        </main>
        <footer class="wrap">
            <span class="brand"
                ><AppLogoIcon class="size-5" /> Pixelforge Studio</span
            ><Link :href="login()">Account login</Link>
        </footer>
    </div>
</template>
<style scoped>
.press {
    --paper: #f6f4ec;
    --text: #192820;
    --sub: #47594d;
    --rule: #bec6b7;
    --ink: #183e31;
    background: var(--paper);
    color: var(--text);
    font-family: inherit;
}
:global(.dark) .press {
    --paper: #141d19;
    --text: #eff1e7;
    --sub: #bdcabe;
    --rule: #415047;
    --ink: #cce7cc;
}
.wrap {
    width: min(100% - clamp(2rem, 8vw, 8rem), 80rem);
    margin-inline: auto;
}
.mast,
.account,
.anchors,
.brand,
.actions,
.ink,
.closing,
footer,
.preview-head {
    display: flex;
    align-items: center;
}
.mast {
    justify-content: space-between;
    gap: 1rem;
    border-bottom: 1px solid var(--rule);
    padding-block: 1rem;
}
.brand {
    gap: 0.55rem;
    font-weight: 680;
    letter-spacing: -0.035em;
    white-space: nowrap;
}
.anchors,
.account,
.actions {
    gap: clamp(0.65rem, 2vw, 2rem);
}
.mast a,
.actions a,
footer a {
    display: inline-flex;
    align-items: center;
    min-height: 44px;
    font-size: 0.9rem;
}
.mast .brand {
    font-size: 1.06rem;
}
.ink {
    justify-content: center;
    gap: 0.65rem;
    background: var(--ink);
    color: var(--paper);
    padding: 0.65rem 1.1rem;
    border-radius: 100px;
    font-weight: 600;
    white-space: nowrap;
}
.ink:hover {
    box-shadow: inset 0 0 0 2px currentColor;
}
a:focus-visible,
:deep(button:focus-visible) {
    outline: 3px solid currentColor;
    outline-offset: 3px;
}
.skip {
    position: absolute;
    left: 1rem;
    top: -6rem;
    z-index: 5;
    background: var(--paper);
    padding: 0.7rem;
}
.skip:focus {
    top: 1rem;
}
.hero,
.spread {
    display: grid;
    align-items: center;
    gap: clamp(1.5rem, 5vw, 5rem);
}
.hero {
    grid-template-columns: 1fr 1.03fr;
    padding-block: clamp(3rem, 7vw, 7rem);
}
h1,
h2 {
    font-family: Georgia, 'Times New Roman', serif;
    font-weight: 400;
    letter-spacing: -0.065em;
    line-height: 1.01;
}
h1 {
    font-size: clamp(3.3rem, 6.8vw, 6.8rem);
}
h2 {
    font-size: clamp(2.6rem, 4.7vw, 4.8rem);
}
em {
    color: var(--ink);
}
.kicker,
.preview-head {
    font-size: 0.72rem;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    font-weight: 650;
}
.kicker {
    color: var(--sub);
    margin-bottom: 1.2rem;
}
.lede,
.body,
.steps p,
.pricing dd,
.note {
    color: var(--sub);
    line-height: 1.6;
}
.lede {
    font-size: clamp(1rem, 1.4vw, 1.25rem);
    max-width: 36ch;
    margin: 1.5rem 0;
}
.actions {
    flex-wrap: wrap;
}
.actions > a:last-child {
    gap: 0.6rem;
    text-decoration: underline;
    text-underline-offset: 0.3em;
}
.process,
.pricing {
    border-block: 1px solid var(--rule);
}
.process > .wrap,
.spread,
.closing {
    padding-block: clamp(2.75rem, 6vw, 6rem);
}
.steps {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 2rem;
    margin-top: 2.25rem;
}
.steps article {
    border-top: 1px solid var(--rule);
    padding-top: 1.25rem;
}
.steps svg {
    color: var(--ink);
}
.steps h3,
.pricing dt {
    font-size: 1.15rem;
    font-weight: 650;
}
.steps h3 {
    margin: 0.8rem 0 0.35rem;
}
.spread {
    grid-template-columns: 0.85fr 1.15fr;
}
.body {
    margin-top: 1rem;
    max-width: 44ch;
}
.note {
    font-size: 0.85rem;
    max-width: 46ch;
    margin-top: 1.5rem;
    border-left: 2px solid var(--rule);
    padding-left: 1rem;
}
.preview {
    min-width: 0;
    padding: clamp(1rem, 3vw, 2.25rem);
    border: 1px solid var(--rule);
    border-radius: 1rem;
}
.preview-head {
    gap: 0.5rem;
    margin-bottom: 1.5rem;
}
.pricing {
    background: color-mix(in srgb, var(--ink) 6%, var(--paper));
}
.pricing dl > div {
    padding-block: 1rem;
    border-bottom: 1px solid var(--rule);
}
.pricing dd {
    margin-top: 0.25rem;
}
.closing,
footer {
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}
footer {
    border-top: 1px solid var(--rule);
    padding-block: 1rem;
}
@media (max-width: 780px) {
    .hero,
    .spread {
        grid-template-columns: minmax(0, 1fr);
    }
    .mast {
        flex-wrap: wrap;
    }
    .anchors {
        order: 3;
        width: 100%;
        justify-content: space-between;
    }
    .steps {
        gap: 1rem;
    }
    .intro {
        max-width: 40rem;
    }
}
@media (max-width: 540px) {
    h1 {
        font-size: clamp(2.8rem, 11.5vw, 3.8rem);
    }

    .steps {
        grid-template-columns: 1fr;
    }
    .mast {
        row-gap: 0;
    }
    .mast .ink {
        padding-inline: 0.7rem;
    }
    .mast .brand {
        font-size: 0.94rem;
    }
    .account {
        gap: 0.6rem;
    }
    .account .ink svg {
        display: none;
    }
    .preview :deep(fieldset) {
        min-width: 0;
    }
}
</style>
