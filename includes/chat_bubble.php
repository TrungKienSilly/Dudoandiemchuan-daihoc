<!-- Chat Bubble Widget - Góc phải dưới -->
<style>
/* Chat Bubble Button */
.chat-bubble-btn {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: white;
    border: none;
    cursor: pointer;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    z-index: 9998;
    animation: float 3s ease-in-out infinite;
    padding: 8px;
    overflow: hidden;
}

.chat-bubble-btn img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
}

.chat-bubble-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.chat-bubble-btn.active {
    transform: scale(1.05);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

@keyframes float {
    0%, 100% { 
        transform: translateY(0px);
    }
    50% { 
        transform: translateY(-8px);
    }
}

/* Chat Box Container */
.chat-box-container {
    position: fixed;
    bottom: 100px;
    right: 30px;
    width: 380px;
    max-width: calc(100vw - 60px);
    height: 550px;
    max-height: calc(100vh - 150px);
    background: white;
    border-radius: 20px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
    display: none;
    flex-direction: column;
    overflow: hidden;
    z-index: 9999;
    animation: slideUp 0.3s ease;
}

.chat-box-container.show {
    display: flex;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Chat Header */
.chat-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chat-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
}

.chat-header .close-btn {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 20px;
    line-height: 1;
    transition: all 0.2s;
}

.chat-header .close-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg);
}

/* Chat Messages */
.chat-messages {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
    background: #f8f9fa;
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.chat-messages::-webkit-scrollbar {
    width: 6px;
}

.chat-messages::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.chat-messages::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 3px;
}

.message {
    display: flex;
    gap: 10px;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.message.user {
    flex-direction: row-reverse;
}

.message-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.message.bot .message-avatar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.message.user .message-avatar {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
}

.message-bubble {
    max-width: 75%;
    padding: 12px 16px;
    border-radius: 18px;
    word-wrap: break-word;
    line-height: 1.5;
}

.message.bot .message-bubble {
    background: white;
    color: #333;
    border: 1px solid #e0e0e0;
}

.message.user .message-bubble {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.message-time {
    font-size: 11px;
    color: #999;
    margin-top: 4px;
}

/* Typing Indicator */
.typing-indicator {
    display: none;
    padding: 12px 16px;
    background: white;
    border-radius: 18px;
    border: 1px solid #e0e0e0;
    width: fit-content;
}

.typing-indicator.show {
    display: block;
}

.typing-indicator span {
    height: 8px;
    width: 8px;
    background: #999;
    border-radius: 50%;
    display: inline-block;
    margin: 0 2px;
    animation: typing 1.4s infinite;
}

.typing-indicator span:nth-child(2) {
    animation-delay: 0.2s;
}

.typing-indicator span:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes typing {
    0%, 60%, 100% { transform: translateY(0); }
    30% { transform: translateY(-10px); }
}

/* Chat Input */
.chat-input-container {
    padding: 15px 20px;
    background: white;
    border-top: 1px solid #e0e0e0;
    display: flex;
    gap: 10px;
}

.chat-input {
    flex: 1;
    padding: 12px 16px;
    border: 2px solid #e0e0e0;
    border-radius: 25px;
    font-size: 14px;
    outline: none;
    transition: border-color 0.2s;
}

.chat-input:focus {
    border-color: #667eea;
}

.chat-send-btn {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    cursor: pointer;
    font-size: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.chat-send-btn:hover:not(:disabled) {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.chat-send-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Welcome Message */
.welcome-message {
    text-align: center;
    padding: 30px 20px;
    color: #666;
}

.welcome-message h4 {
    color: #667eea;
    margin-bottom: 10px;
}

.quick-questions {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 15px;
}

.quick-question-btn {
    padding: 10px 15px;
    background: white;
    border: 2px solid #667eea;
    color: #667eea;
    border-radius: 20px;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.2s;
    text-align: left;
}

.quick-question-btn:hover {
    background: #667eea;
    color: white;
    transform: translateX(5px);
}

/* Responsive */
@media (max-width: 768px) {
    .chat-box-container {
        width: calc(100vw - 40px);
        height: calc(100vh - 140px);
        right: 20px;
        bottom: 90px;
    }
    
    .chat-bubble-btn {
        bottom: 20px;
        right: 20px;
        width: 55px;
        height: 55px;
        font-size: 24px;
    }
}
</style>

<!-- Chat Bubble Button -->
<button class="chat-bubble-btn" id="chatBubbleBtn" title="Chat với AI">
    <img src="<?php echo $base_path ?? ''; ?>img/box-chat.jpg" alt="Chat AI">
</button>

<!-- Chat Box -->
<div class="chat-box-container" id="chatBox">
    <div class="chat-header">
        <div>
            <h3>🤖 AI Tư vấn</h3>
            <small style="opacity: 0.9;">Trợ lý tuyển sinh thông minh</small>
        </div>
        <button class="close-btn" id="closeChatBtn">×</button>
    </div>
    
    <div class="chat-messages" id="chatMessages">
        <div class="welcome-message">
            <h4>Xin chào! 👋</h4>
            <p>Tôi là trợ lý AI. Tôi có thể giúp bạn:</p>
            <div class="quick-questions">
                <button class="quick-question-btn" onclick="sendQuickQuestion('Điểm chuẩn năm nay thay đổi thế nào?')">
                    Điểm chuẩn năm nay thay đổi thế nào?
                </button>
                <button class="quick-question-btn" onclick="sendQuickQuestion('Làm sao để chọn ngành phù hợp?')">
                    Làm sao để chọn ngành phù hợp?
                </button>
                <button class="quick-question-btn" onclick="sendQuickQuestion('Nên chọn bao nhiêu nguyện vọng?')">
                    Nên chọn bao nhiêu nguyện vọng?
                </button>
            </div>
        </div>
        
        <div class="message bot" style="display: none;">
            <div class="message-avatar">🤖</div>
            <div>
                <div class="message-bubble typing-indicator">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="chat-input-container">
        <input 
            type="text" 
            class="chat-input" 
            id="chatInput" 
            placeholder="Nhập câu hỏi của bạn..."
            autocomplete="off"
        >
        <button class="chat-send-btn" id="chatSendBtn">
            ➤
        </button>
    </div>
</div>

<script>
const chatBubbleBtn = document.getElementById('chatBubbleBtn');
const chatBox = document.getElementById('chatBox');
const closeChatBtn = document.getElementById('closeChatBtn');
const chatMessages = document.getElementById('chatMessages');
const chatInput = document.getElementById('chatInput');
const chatSendBtn = document.getElementById('chatSendBtn');

let isFirstMessage = true;

// Toggle chat box
chatBubbleBtn.addEventListener('click', () => {
    chatBox.classList.toggle('show');
    chatBubbleBtn.classList.toggle('active');
    
    if (chatBox.classList.contains('show')) {
        chatInput.focus();
    }
});

closeChatBtn.addEventListener('click', () => {
    chatBox.classList.remove('show');
    chatBubbleBtn.classList.remove('active');
});

// Send message
chatSendBtn.addEventListener('click', sendMessage);
chatInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

function sendQuickQuestion(question) {
    chatInput.value = question;
    sendMessage();
}

async function sendMessage() {
    const message = chatInput.value.trim();
    if (!message) return;
    
    // Hide welcome message on first message
    if (isFirstMessage) {
        const welcomeMsg = chatMessages.querySelector('.welcome-message');
        if (welcomeMsg) welcomeMsg.style.display = 'none';
        isFirstMessage = false;
    }
    
    // Add user message
    addMessage(message, 'user');
    chatInput.value = '';
    
    // Disable input
    chatInput.disabled = true;
    chatSendBtn.disabled = true;
    
    // Show typing indicator
    showTypingIndicator();
    
    try {
        // Call AI API
        console.log('[CHAT DEBUG] Sending message:', message);
        
        const response = await fetch('http://localhost:5000/chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                message: message,
                provider: 'groq' // Dùng Groq vì Gemini hết quota
            })
        });
        
        console.log('[CHAT DEBUG] Response status:', response.status);
        const data = await response.json();
        console.log('[CHAT DEBUG] Response data:', data);
        
        // Hide typing indicator
        hideTypingIndicator();
        
        if (data.success) {
            console.log('[CHAT DEBUG] Adding bot message:', data.response);
            addMessage(data.response, 'bot');
        } else {
            console.error('[CHAT DEBUG] Error from API:', data.error);
            addMessage('Xin lỗi, có lỗi xảy ra: ' + (data.error || 'Vui lòng thử lại sau.'), 'bot');
        }
    } catch (error) {
        console.error('[CHAT DEBUG] Catch error:', error);
        hideTypingIndicator();
        addMessage('Không thể kết nối với AI. Vui lòng kiểm tra server Python đang chạy.', 'bot');
    } finally {
        // Re-enable input
        chatInput.disabled = false;
        chatSendBtn.disabled = false;
        chatInput.focus();
    }
}

function addMessage(text, sender) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${sender}`;
    
    const now = new Date();
    const timeStr = now.getHours().toString().padStart(2, '0') + ':' + 
                    now.getMinutes().toString().padStart(2, '0');
    
    messageDiv.innerHTML = `
        <div class="message-avatar">${sender === 'bot' ? '🤖' : '👤'}</div>
        <div>
            <div class="message-bubble">${text}</div>
            <div class="message-time">${timeStr}</div>
        </div>
    `;
    
    chatMessages.appendChild(messageDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function showTypingIndicator() {
    const indicator = chatMessages.querySelector('.typing-indicator');
    if (indicator) {
        indicator.parentElement.parentElement.style.display = 'flex';
        indicator.classList.add('show');
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
}

function hideTypingIndicator() {
    const indicator = chatMessages.querySelector('.typing-indicator');
    if (indicator) {
        indicator.classList.remove('show');
        indicator.parentElement.parentElement.style.display = 'none';
    }
}

// Close chat when clicking outside
document.addEventListener('click', (e) => {
    if (!chatBox.contains(e.target) && !chatBubbleBtn.contains(e.target)) {
        if (chatBox.classList.contains('show')) {
            chatBox.classList.remove('show');
            chatBubbleBtn.classList.remove('active');
        }
    }
});
</script>
