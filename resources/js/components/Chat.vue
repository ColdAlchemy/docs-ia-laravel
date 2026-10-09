<script setup>
import { ref, nextTick } from 'vue';

const props = defineProps({ endpoint: { type: String, required: true } });

const pergunta = ref('');
const carregando = ref(false);
const erro = ref('');
// Cada item: { role: 'user' | 'assistant', content, fontes?, passos?, avaliacao? }
const mensagens = ref([]);
const lista = ref(null);

async function enviar() {
    const texto = pergunta.value.trim();
    if (texto.length < 3 || carregando.value) return;

    erro.value = '';
    // Histórico enviado ao servidor: só texto das mensagens anteriores.
    const historico = mensagens.value.map(({ role, content }) => ({ role, content }));

    mensagens.value.push({ role: 'user', content: texto });
    pergunta.value = '';
    carregando.value = true;

    try {
        const resposta = await fetch(props.endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ pergunta: texto, historico }),
        });
        const dados = await resposta.json();
        if (!resposta.ok) throw new Error(dados.message || 'Erro ao consultar o assistente.');

        mensagens.value.push({
            role: 'assistant',
            content: dados.resposta,
            fontes: dados.fontes,
            passos: dados.passos,
            avaliacao: dados.avaliacao,
        });
    } catch (e) {
        erro.value = e.message;
    } finally {
        carregando.value = false;
        await nextTick();
        lista.value?.scrollTo({ top: lista.value.scrollHeight });
    }
}
</script>

<template>
    <div class="chat">
        <div ref="lista" class="mensagens">
            <p v-if="!mensagens.length" class="mut">
                Pergunte algo sobre os documentos enviados. Ex.: "Qual o prazo para pagamento?"
            </p>

            <div v-for="(m, i) in mensagens" :key="i" :class="['msg', m.role]">
                <div class="texto">{{ m.content }}</div>

                <template v-if="m.role === 'assistant'">
                    <div v-if="m.avaliacao" :class="['selo', m.avaliacao.fundamentada ? 'ok' : 'aviso']">
                        {{ m.avaliacao.fundamentada ? 'Fundamentada nos documentos' : 'Atenção' }}: {{ m.avaliacao.motivo }}
                    </div>
                    <details v-if="m.fontes?.length">
                        <summary>{{ m.fontes.length }} fonte(s)</summary>
                        <ul>
                            <li v-for="f in m.fontes" :key="f.trecho_id">
                                <strong>[T{{ f.trecho_id }}] {{ f.titulo }}</strong>
                                <span class="mut"> (relevância {{ f.score }})</span>
                                <div>{{ f.texto }}</div>
                            </li>
                        </ul>
                    </details>
                    <details v-if="m.passos?.length">
                        <summary>Passos do agente</summary>
                        <ul>
                            <li v-for="(p, j) in m.passos" :key="j">
                                <code>{{ p.ferramenta }}</code> {{ JSON.stringify(p.entrada) }}
                            </li>
                        </ul>
                    </details>
                </template>
            </div>

            <p v-if="carregando" class="mut">Consultando…</p>
            <p v-if="erro" class="erro">{{ erro }}</p>
        </div>

        <form class="envio" @submit.prevent="enviar">
            <input v-model="pergunta" placeholder="Digite sua pergunta" maxlength="1000" :disabled="carregando" />
            <button type="submit" :disabled="carregando">Enviar</button>
        </form>
    </div>
</template>
