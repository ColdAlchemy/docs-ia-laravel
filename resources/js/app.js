import { createApp } from 'vue';
import Chat from './components/Chat.vue';

const el = document.getElementById('chat-app');
if (el) {
    createApp(Chat, { endpoint: el.dataset.endpoint }).mount(el);
}
