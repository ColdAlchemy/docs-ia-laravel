<script setup>
import { computed, nextTick, ref } from 'vue';

const props = defineProps({ config: { type: Object, required: true } });

const modo = props.config.modo; // 'leitor' (um documento) ou 'consulta' (o espaço todo)
const mensagens = ref(props.config.mensagens ?? []);
const conversaId = ref(props.config.conversaId ?? null);
const pergunta = ref('');
const carregando = ref(false);
const erro = ref('');
const novas = ref(new Set()); // respostas recém-chegadas: o carimbo "bate" só nelas
const fim = ref(null);

const semMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * Transforma o texto da resposta em parágrafos/itens com referências [T<id>] viradas
 * em notas de rodapé numeradas pela ordem de aparição. Citações de trechos que o
 * agente não recuperou ficam marcadas como inválidas.
 */
function analisar(m) {
    const fontes = new Map((m.fontes ?? []).map((f) => [f.trecho_id, f]));
    const numeros = new Map();
    const notas = [];

    const partir = (linha) => {
        const partes = [];
        let ultimo = 0;
        for (const achado of linha.matchAll(/\[T(\d+)\]/g)) {
            if (achado.index > ultimo) partes.push({ texto: linha.slice(ultimo, achado.index) });
            const id = Number(achado[1]);
            const fonte = fontes.get(id);
            if (!fonte) {
                partes.push({ invalida: id });
            } else {
                if (!numeros.has(id)) {
                    numeros.set(id, numeros.size + 1);
                    notas.push({ n: numeros.size, fonte });
                }
                partes.push({ ref: numeros.get(id), fonte });
            }
            ultimo = achado.index + achado[0].length;
        }
        if (ultimo < linha.length) partes.push({ texto: linha.slice(ultimo) });
        return partes;
    };

    const grupos = [];
    for (const linha of m.conteudo.split('\n')) {
        if (!linha.trim()) continue;
        const item = /^\s*[-•]\s+/.test(linha);
        const partes = partir(linha.replace(/^\s*[-•]\s+/, ''));
        const atual = grupos[grupos.length - 1];
        if (item && atual?.lista) atual.itens.push(partes);
        else grupos.push({ lista: item, itens: [partes] });
    }
    return { grupos, notas };
}

const lidas = computed(() => mensagens.value.map((m) => (m.papel === 'assistant' ? analisar(m) : null)));

function urlFonte(fonte) {
    return props.config.urlDocumento
        .replace('__ID__', fonte.documento_id)
        .replace('__TRECHO__', fonte.trecho_id);
}

function verNoDocumento(fonte) {
    window.dispatchEvent(new CustomEvent('docsia:destacar', { detail: { trecho: fonte.trecho_id } }));
}

function trecho(texto) {
    return texto.length > 230 ? `${texto.slice(0, 227)}…` : texto;
}

async function enviar() {
    const texto = pergunta.value.trim();
    if (carregando.value || !props.config.pronto) return;
    if (texto.length < 3) {
        erro.value = 'A pergunta precisa ter pelo menos 3 caracteres.';
        return;
    }

    erro.value = '';
    carregando.value = true;
    mensagens.value.push({ id: `p${Date.now()}`, papel: 'user', conteudo: texto });
    pergunta.value = '';
    rolarAoFim();

    try {
        const resposta = await fetch(props.config.endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({
                pergunta: texto,
                conversa_id: conversaId.value,
                documento_id: conversaId.value ? null : props.config.documentoId,
            }),
        });
        const dados = await resposta.json().catch(() => ({}));

        if (resposta.status === 422) throw new Error(Object.values(dados.errors ?? {})[0]?.[0] ?? 'Pergunta inválida.');
        if (resposta.status === 429) throw new Error('Muitas perguntas em pouco tempo. Espere um minuto e tente de novo.');
        if (resposta.status === 419) throw new Error('A sessão expirou. Recarregue a página e entre de novo.');
        if (!resposta.ok) throw new Error('Não foi possível consultar agora. Tente de novo em instantes.');

        const primeira = !conversaId.value;
        conversaId.value = dados.conversa_id;
        novas.value.add(dados.mensagem.id);
        mensagens.value.push(dados.mensagem);

        if (primeira && modo === 'consulta') history.replaceState(null, '', `/consultas/${dados.conversa_id}`);
    } catch (e) {
        erro.value = e.message;
        // devolve o texto para a pessoa não perder o que digitou
        mensagens.value.pop();
        pergunta.value = texto;
    } finally {
        carregando.value = false;
        rolarAoFim();
    }
}

async function rolarAoFim() {
    await nextTick();
    fim.value?.scrollIntoView({ block: 'nearest', behavior: semMovimento ? 'auto' : 'smooth' });
}

function aoTeclar(e) {
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
        e.preventDefault();
        enviar();
    }
}

function nova() {
    mensagens.value = [];
    conversaId.value = null;
    erro.value = '';
}
</script>

<template>
    <div class="perguntas">
        <div aria-live="polite" role="log" aria-label="Perguntas e respostas">
            <div v-for="(m, i) in mensagens" :key="m.id" class="pergunta-item">
                <div v-if="m.papel === 'user'" class="requerimento">
                    <p class="legenda">Pergunta</p>
                    {{ m.conteudo }}
                </div>

                <div v-else>
                    <div class="parecer">
                        <template v-for="(g, gi) in lidas[i].grupos" :key="gi">
                            <ul v-if="g.lista">
                                <li v-for="(partes, li) in g.itens" :key="li">
                                    <template v-for="(p, pi) in partes" :key="pi">
                                        <template v-if="p.texto !== undefined">{{ p.texto }}</template>
                                        <sup v-else-if="p.ref" class="refnota">
                                            <button v-if="modo === 'leitor'" type="button" :aria-label="`Nota ${p.ref}: mostrar o trecho no documento`" @click="verNoDocumento(p.fonte)">{{ p.ref }}</button>
                                            <a v-else :href="urlFonte(p.fonte)" :aria-label="`Nota ${p.ref}: abrir o documento no trecho citado`">{{ p.ref }}</a>
                                        </sup>
                                        <sup v-else class="refnota invalida" title="Citação de um trecho que o agente não recuperou">[T{{ p.invalida }}]</sup>
                                    </template>
                                </li>
                            </ul>
                            <p v-else>
                                <template v-for="(p, pi) in g.itens[0]" :key="pi">
                                    <template v-if="p.texto !== undefined">{{ p.texto }}</template>
                                    <sup v-else-if="p.ref" class="refnota">
                                        <button v-if="modo === 'leitor'" type="button" :aria-label="`Nota ${p.ref}: mostrar o trecho no documento`" @click="verNoDocumento(p.fonte)">{{ p.ref }}</button>
                                        <a v-else :href="urlFonte(p.fonte)" :aria-label="`Nota ${p.ref}: abrir o documento no trecho citado`">{{ p.ref }}</a>
                                    </sup>
                                    <sup v-else class="refnota invalida" title="Citação de um trecho que o agente não recuperou">[T{{ p.invalida }}]</sup>
                                </template>
                            </p>
                        </template>
                    </div>

                    <div v-if="m.avaliacao" class="selagem">
                        <span :class="['carimbo', m.avaliacao.fundamentada ? '' : 'reprovado', novas.has(m.id) ? 'bater' : '']">
                            {{ m.avaliacao.fundamentada ? 'Fundamentada' : 'Sem fundamento' }}
                        </span>
                        <span class="motivo">{{ m.avaliacao.motivo }}</span>
                    </div>

                    <div v-if="lidas[i].notas.length" class="notas-rodape">
                        <ol>
                            <li v-for="nota in lidas[i].notas" :key="nota.n">
                                <span class="n">{{ nota.n }}.</span>
                                <span>
                                    <span class="fonte-titulo">{{ nota.fonte.titulo }}<template v-if="nota.fonte.registro"> · reg. {{ nota.fonte.registro }}</template></span>
                                    <q>{{ trecho(nota.fonte.texto) }}</q>
                                    <button v-if="modo === 'leitor'" type="button" class="botao-link" @click="verNoDocumento(nota.fonte)">ver no documento</button>
                                    <a v-else :href="urlFonte(nota.fonte)">abrir no documento</a>
                                </span>
                            </li>
                        </ol>
                    </div>

                    <details v-if="m.passos?.length" class="passos">
                        <summary>Como o agente chegou nisso ({{ m.passos.length }} {{ m.passos.length === 1 ? 'passo' : 'passos' }})</summary>
                        <ul>
                            <li v-for="(p, j) in m.passos" :key="j"><code>{{ p.ferramenta }}</code> {{ JSON.stringify(p.entrada) }}</li>
                        </ul>
                    </details>
                </div>
            </div>
            <p v-if="carregando" class="consultando" role="status">Consultando os documentos…</p>
            <div ref="fim"></div>
        </div>

        <form :class="['form-pergunta', { 'sem-conversa': !mensagens.length }]" novalidate @submit.prevent="enviar">
            <div v-if="!mensagens.length && config.pronto && config.sugestoes?.length" class="sugestoes" aria-label="Exemplos de pergunta">
                <button v-for="s in config.sugestoes" :key="s" type="button" @click="pergunta = s">{{ s }}</button>
            </div>
            <label for="pergunta-texto">Sua pergunta</label>
            <textarea
                id="pergunta-texto"
                v-model="pergunta"
                name="pergunta"
                maxlength="1000"
                autocomplete="off"
                :disabled="!config.pronto"
                :aria-invalid="erro ? 'true' : null"
                :aria-describedby="erro ? 'pergunta-erro pergunta-dica' : 'pergunta-dica'"
                @keydown="aoTeclar"
            ></textarea>
            <p v-if="erro" id="pergunta-erro" class="erro-campo" role="alert">{{ erro }}</p>
            <p id="pergunta-dica" class="dica">Enter envia · Shift+Enter quebra a linha.</p>
            <div class="acoes">
                <button type="submit" class="botao" :disabled="carregando || !config.pronto">{{ carregando ? 'Consultando…' : 'Perguntar' }}</button>
                <button v-if="mensagens.length && modo === 'leitor'" type="button" class="botao-link" @click="nova">Começar nova conversa</button>
            </div>
        </form>
    </div>
</template>
