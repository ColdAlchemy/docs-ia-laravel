import { createApp } from 'vue';
import Perguntas from './components/Perguntas.vue';

const semMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Perguntas e respostas (leitor e consultas)
const el = document.getElementById('perguntas-app');
if (el) {
    createApp(Perguntas, { config: JSON.parse(el.dataset.config) }).mount(el);
}

// Destaca no documento o trecho citado por uma nota de rodapé (também vale para ?trecho=ID).
function destacar(id) {
    const bloco = document.getElementById(`T${id}`);
    if (!bloco) return;
    document.querySelectorAll('.bloco.marcado').forEach((b) => b.classList.remove('marcado'));
    bloco.scrollIntoView({ block: 'center', behavior: semMovimento ? 'auto' : 'smooth' });
    bloco.focus({ preventScroll: true });
    // reinicia a transição do marca-texto
    requestAnimationFrame(() => requestAnimationFrame(() => bloco.classList.add('marcado')));
}
window.addEventListener('docsia:destacar', (e) => destacar(e.detail.trecho));
const doUrl = new URLSearchParams(location.search).get('trecho');
if (doUrl) window.addEventListener('load', () => destacar(doUrl));

// Confirmação de ações destrutivas: <form data-confirmar="mensagem">
const dialogo = document.getElementById('confirmar');
if (dialogo) {
    document.addEventListener('submit', (e) => {
        const form = e.target.closest('form[data-confirmar]');
        if (!form || form.dataset.confirmado) return;
        e.preventDefault();
        document.getElementById('confirmar-texto').textContent = form.dataset.confirmar;
        dialogo.returnValue = '';
        dialogo.showModal();
        dialogo.addEventListener('close', () => {
            if (dialogo.returnValue === 'ok') {
                form.dataset.confirmado = '1';
                form.requestSubmit();
            }
        }, { once: true });
    });
}

// Páginas com processamento em andamento se atualizam sozinhas.
const atualizar = document.querySelector('[data-atualizar]');
if (atualizar) {
    let tentativas = 0;
    const intervalo = Number(atualizar.dataset.atualizar) || 3000;
    const timer = setInterval(() => {
        if (++tentativas > 40) return clearInterval(timer);
        location.reload();
    }, intervalo);
}

// Em formulários com erro, o foco vai para o primeiro campo inválido.
document.querySelector('[aria-invalid="true"]')?.focus();

// Menu de espaços: fecha com Esc ou ao clicar fora.
const menuEspacos = document.querySelector('.espaco-menu');
if (menuEspacos) {
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && menuEspacos.open) {
            menuEspacos.open = false;
            menuEspacos.querySelector('summary').focus();
        }
    });
    document.addEventListener('click', (e) => {
        if (menuEspacos.open && !menuEspacos.contains(e.target)) menuEspacos.open = false;
    });
}

// Avisa antes de sair de um formulário com texto ainda não enviado.
const protegido = document.querySelector('form[data-proteger-saida]');
if (protegido) {
    let enviando = false;
    let alterado = false;
    protegido.addEventListener('input', () => { alterado = true; });
    protegido.addEventListener('submit', () => { enviando = true; });
    window.addEventListener('beforeunload', (e) => {
        if (alterado && !enviando) e.preventDefault();
    });
}

// Botão "Imprimir" do relatório.
document.querySelector('[data-imprimir]')?.addEventListener('click', () => window.print());
