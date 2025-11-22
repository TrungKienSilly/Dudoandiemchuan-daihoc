#!/usr/bin/env python3
"""
AI API Backend for Tuyensinh System (Support Gemini & Groq)
Chạy bằng: python ai_api.py
Hoặc: python -m flask run --port=5000
"""

from flask import Flask, request, jsonify
from flask_cors import CORS
import os
import json
import re
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

# Import AI SDKs
try:
    from google import genai
    GEMINI_AVAILABLE = True
except ImportError:
    print("WARNING: google-genai không được cài đặt. Gemini sẽ không khả dụng.")
    GEMINI_AVAILABLE = False

try:
    from groq import Groq
    GROQ_AVAILABLE = True
except ImportError:
    print("WARNING: groq không được cài đặt. Groq sẽ không khả dụng.")
    GROQ_AVAILABLE = False

app = Flask(__name__)
CORS(app)  # Cho phép CORS để PHP/JavaScript có thể gọi

# Cấu hình API keys từ environment variables
GEMINI_API_KEY = os.getenv('GEMINI_API_KEY', '')
GROQ_API_KEY = os.getenv('GROQ_API_KEY', '')

# Mặc định sử dụng Groq (nhanh và miễn phí hơn)
DEFAULT_PROVIDER = 'groq'  # Có thể đổi thành 'gemini'

# Khởi tạo clients
if GEMINI_AVAILABLE:
    os.environ['GEMINI_API_KEY'] = GEMINI_API_KEY
    gemini_client = genai.Client(api_key=GEMINI_API_KEY)
    print("Gemini client initialized")

if GROQ_AVAILABLE:
    groq_client = Groq(api_key=GROQ_API_KEY)
    print("Groq client initialized")

def call_ai_model(prompt, provider=None):
    """
    Gọi AI model (Gemini hoặc Groq)
    
    Args:
        prompt: Text prompt
        provider: 'gemini', 'groq', hoặc None (dùng DEFAULT_PROVIDER)
    
    Returns:
        tuple: (success, response_text_or_error)
    """
    if provider is None:
        provider = DEFAULT_PROVIDER
    
    provider = provider.lower()
    
    # Groq
    if provider == 'groq':
        if not GROQ_AVAILABLE:
            return (False, "Groq SDK không được cài đặt")
        
        try:
            print(f"[INFO] Đang gọi Groq API (Model: llama-3.3-70b-versatile)...")
            response = groq_client.chat.completions.create(
                model="llama-3.3-70b-versatile",
                messages=[
                    {"role": "system", "content": "Bạn là chuyên gia tư vấn tuyển sinh đại học, phân tích dữ liệu chính xác và đưa ra lời khuyên hữu ích."},
                    {"role": "user", "content": prompt}
                ],
                temperature=0.7,
                max_tokens=500,
            )
            return (True, response.choices[0].message.content)
        except Exception as e:
            print(f"[ERROR] Groq API failed: {str(e)}")
            return (False, str(e))
    
    # Gemini
    elif provider == 'gemini':
        if not GEMINI_AVAILABLE:
            return (False, "Gemini SDK không được cài đặt")
        
        try:
            print(f"[INFO] Đang gọi Gemini API (Model: gemini-2.0-flash-exp)...")
            response = gemini_client.models.generate_content(
                model='gemini-2.0-flash-exp',
                contents=prompt
            )
            return (True, response.text)
        except Exception as e:
            print(f"[ERROR] Gemini API failed: {str(e)}")
            return (False, str(e))
    
    else:
        return (False, f"Provider không hợp lệ: {provider}. Chọn 'gemini' hoặc 'groq'")

def parse_ai_response(text):
    """Parse response từ AI theo format: 1. Phân tích xu hướng → 2. Lời khuyên (3 items) → 3. Kết luận"""
    result = {
        'probability': None,
        'trend_analysis': '',
        'recommendations': [],
        'conclusion': '',
        'full_text': text
    }
    
    # Hàm helper để clean text
    def clean_text(text):
        # Loại bỏ markdown ** **
        text = re.sub(r'\*\*(.+?)\*\*', r'\1', text)
        # Loại bỏ ### heading
        text = re.sub(r'###\s+', '', text)
        # Loại bỏ số thứ tự đầu dòng (1., 2., 3., etc)
        text = re.sub(r'^\d+\.\s+', '', text)
        return text.strip()
    
    # Trích xuất xác suất
    prob_match = re.search(r'(\d+)\s*%', text)
    if prob_match:
        result['probability'] = int(prob_match.group(1))
    
    # Tìm vị trí của từng section
    trend_match = re.search(r'(?:^|\n)(?:###\s*)?1\.\s*(?:Phân tích xu hướng|PHÂN TÍCH XU HƯỚNG)', text, re.IGNORECASE | re.MULTILINE)
    advice_match = re.search(r'(?:^|\n)(?:###\s*)?2\.\s*(?:Lời khuyên|LỜI KHUYÊN)', text, re.IGNORECASE | re.MULTILINE)
    conclusion_match = re.search(r'(?:^|\n)(?:###\s*)?3\.\s*(?:Kết luận|KẾT LUẬN)', text, re.IGNORECASE | re.MULTILINE)
    
    # === 1. PHÂN TÍCH XU HƯỚNG ===
    if trend_match and advice_match:
        trend_text = text[trend_match.end():advice_match.start()].strip()
    elif trend_match:
        # Lấy từ sau "1. Phân tích" đến trước "2." hoặc 200 ký tự
        remaining = text[trend_match.end():]
        next_section = re.search(r'\n(?:###\s*)?[23]\.\s*', remaining)
        if next_section:
            trend_text = remaining[:next_section.start()].strip()
        else:
            trend_text = remaining[:400].strip()
    else:
        trend_text = text[:300].strip()
    
    # Clean và format trend
    trend_lines = [line.strip() for line in trend_text.split('\n') if line.strip()]
    trend_lines = [clean_text(line) for line in trend_lines if not re.match(r'^[23]\.|^###', line)]
    result['trend_analysis'] = '\n'.join(trend_lines[:10])  # Tối đa 10 dòng
    
    # === 2. LỜI KHUYÊN ===
    if advice_match and conclusion_match:
        advice_text = text[advice_match.end():conclusion_match.start()].strip()
    elif advice_match:
        remaining = text[advice_match.end():]
        next_section = re.search(r'\n(?:###\s*)?3\.\s*', remaining)
        if next_section:
            advice_text = remaining[:next_section.start()].strip()
        else:
            advice_text = remaining[:500].strip()
    else:
        advice_text = ''
    
    # Parse lời khuyên - tìm các bullet points hoặc dòng có nội dung
    if advice_text:
        # Tìm tất cả các dòng bắt đầu bằng - hoặc * hoặc có "Lời khuyên"
        advice_patterns = [
            r'[-•*]\s*(.+)',  # Bullet points
            r'(?:Lời khuyên|Khuyên)\s*\d*[:\s]*(.+)',  # "Lời khuyên 1: ..."
            r'^(?!3\.)(.{20,})',  # Dòng có nội dung đủ dài
        ]
        
        advice_items = []
        for line in advice_text.split('\n'):
            line = line.strip()
            if not line or re.match(r'^[23]\.|^###', line):
                continue
            
            # Thử match với các pattern
            for pattern in advice_patterns:
                match = re.match(pattern, line, re.IGNORECASE)
                if match:
                    content = match.group(1) if match.lastindex else line
                    content = clean_text(content)
                    if len(content) > 15:  # Chỉ lấy nội dung đủ dài
                        advice_items.append(content)
                    break
        
        # Lấy đúng 3 lời khuyên đầu tiên
        if len(advice_items) >= 3:
            result['recommendations'] = advice_items[:3]
        elif len(advice_items) > 0:
            # Nếu không đủ 3, bổ sung thêm
            result['recommendations'] = advice_items
            while len(result['recommendations']) < 3:
                default_advice = [
                    'Xem xét kỹ nguyện vọng và điểm số của mình',
                    'Chuẩn bị các nguyện vọng dự phòng',
                    'Tham khảo ý kiến từ giáo viên và gia đình'
                ]
                result['recommendations'].append(default_advice[len(result['recommendations'])])
    
    # Nếu không parse được, dùng default
    if not result['recommendations']:
        result['recommendations'] = [
            'Xem xét kỹ nguyện vọng và điểm số của mình',
            'Chuẩn bị các nguyện vọng dự phòng',
            'Tham khảo ý kiến từ giáo viên và gia đình'
        ]
    
    # === 3. KẾT LUẬN ===
    if conclusion_match:
        conclusion_text = text[conclusion_match.end():].strip()
    else:
        # Fallback: lấy 3 câu cuối
        sentences = [s.strip() for s in re.split(r'[.!?]\s+', text) if len(s.strip()) > 20]
        conclusion_text = '. '.join(sentences[-3:]) + '.' if sentences else ''
    
    # Clean conclusion
    conclusion_lines = [line.strip() for line in conclusion_text.split('\n') if line.strip()]
    conclusion_lines = [clean_text(line) for line in conclusion_lines if not line.startswith('###')]
    result['conclusion'] = '\n'.join(conclusion_lines[:6])  # Tối đa 6 dòng
    
    # Nếu conclusion trống, lấy từ cuối text
    if not result['conclusion']:
        last_parts = text.split('\n')[-3:]
        result['conclusion'] = '\n'.join([clean_text(p) for p in last_parts if p.strip()])
    
    return result

@app.route('/analyze', methods=['POST'])
def analyze():
    """API endpoint để phân tích tuyển sinh"""
    try:
        data = request.get_json()
        
        # Validate input
        if not data:
            return jsonify({'success': False, 'error': 'Không có dữ liệu'}), 400
        
        # Lấy provider từ request (nếu có), mặc định dùng DEFAULT_PROVIDER
        provider = data.get('provider', DEFAULT_PROVIDER)
        
        # Tạo prompt từ dữ liệu
        total_score = data.get('totalScore', 0)
        exam_block = data.get('examBlock', '')
        university_name = data.get('universityName', '')
        major_name = data.get('majorName', '')
        historical_scores = data.get('historicalScores', [])
        
        # Tạo prompt
        prompt = f"""Phân tích tuyển sinh đại học:

Thí sinh: Khối {exam_block}, tổng điểm {total_score}
Ngành: {major_name} - {university_name}

Điểm chuẩn các năm:
"""
        
        for score in historical_scores:
            prompt += f"- Năm {score['year']}: {score['score']} điểm\n"
        
        prompt += f"""
Hãy phân tích và tư vấn theo ĐÚNG FORMAT sau (bắt buộc):

1. Phân tích xu hướng (chi tiết, ít nhất 300 từ, không xuất hiện dấu : ở đầu đoạn)
Phân tích xu hướng điểm chuẩn ngành {major_name} tại {university_name} qua các năm (tăng/giảm/ổn định). Phân tích điểm của thí sinh với điểm chuẩn cùng ngành của các trường khác cùng khối. So sánh điểm {total_score} của thí sinh với điểm chuẩn. Đánh giá triển vọng ngành học trong tương lai.

2. Lời khuyên
Tôi chỉ muốn bạn đưa ra ĐÚNG 3 lời khuyên thôi, không cần câu dẫn hay giải thích thêm:
(về điểm số và nguyện vọng)
(về chuẩn bị phương án dự phòng)
(về tham khảo ý kiến)

3. Kết luận (ít nhất 100 từ, không xuất hiện dấu : ở đầu đoạn)
Nên giữ hay đổi nguyện vọng này? Tại sao?

LƯU Ý: Trả lời NGẮN GỌN, rõ ràng. Phần "Lời khuyên" chỉ ghi 3 dòng, không thêm bất kỳ câu dẫn nào."""
        
        print(f"[PROMPT] {prompt[:300]}...")
        
        # Gọi AI Model
        success, ai_text = call_ai_model(prompt, provider)
        
        if not success:
            return jsonify({
                'success': False,
                'error': f'Lỗi từ {provider.upper()} API: {ai_text}',
                'provider': provider
            }), 500
        
        print(f"[RESPONSE] {ai_text[:500]}...")
        
        # Parse response
        analysis = parse_ai_response(ai_text)
        
        # Debug: Log parsed results
        print(f"[PARSED] Trend: {len(analysis['trend_analysis'])} chars")
        print(f"[PARSED] Recommendations: {len(analysis['recommendations'])} items")
        print(f"[PARSED] Conclusion: {len(analysis['conclusion'])} chars")
        
        return jsonify({
            'success': True,
            'analysis': analysis,
            'raw_response': ai_text[:500],
            'provider': provider  # Trả về provider đã dùng
        })
        
    except Exception as e:
        print(f"[ERROR] {str(e)}")
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500

@app.route('/chat', methods=['POST'])
def chat():
    """API endpoint cho chat bot - trả lời câu hỏi tự do"""
    try:
        data = request.get_json()
        
        # Validate input
        if not data or 'message' not in data:
            return jsonify({'success': False, 'error': 'Không có tin nhắn'}), 400
        
        user_message = data.get('message', '').strip()
        provider = data.get('provider', DEFAULT_PROVIDER)
        
        if not user_message:
            return jsonify({'success': False, 'error': 'Tin nhắn trống'}), 400
        
        # Tạo prompt cho chat
        prompt = f"""Bạn là chuyên gia tư vấn tuyển sinh đại học tại Việt Nam. 
Hãy trả lời câu hỏi sau một cách chuyên nghiệp, hữu ích và ngắn gọn (3-5 câu):

Câu hỏi: {user_message}

Lưu ý: 
- Trả lời bằng tiếng Việt
- Ngắn gọn, súc tích
- Đưa ra thông tin thực tế và hữu ích
- Nếu không chắc chắn, khuyên người dùng tham khảo thêm"""
        
        print(f"[CHAT] User: {user_message[:100]}...")
        
        # Gọi AI
        success, ai_response = call_ai_model(prompt, provider)
        
        if not success:
            return jsonify({
                'success': False,
                'error': f'Lỗi từ {provider.upper()}: {ai_response}',
                'provider': provider
            }), 500
        
        print(f"[CHAT] AI: {ai_response[:100]}...")
        
        return jsonify({
            'success': True,
            'response': ai_response.strip(),
            'provider': provider
        })
        
    except Exception as e:
        print(f"[CHAT ERROR] {str(e)}")
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500

@app.route('/health', methods=['GET'])
def health():
    """Health check endpoint"""
    return jsonify({'status': 'ok', 'service': 'AI Backend (Gemini & Groq)'})

if __name__ == '__main__':
    print("=" * 60)
    print("AI Backend Server (Gemini & Groq)")
    print("=" * 60)
    print("Running on: http://localhost:5000")
    print("Endpoints:")
    print("   - POST /analyze   : Phân tích tuyển sinh")
    print("   - POST /chat      : Chat bot AI")
    print("   - GET  /health    : Health check")
    print("=" * 60)
    print(f"Default Provider: {DEFAULT_PROVIDER.upper()}")
    print(f"Available Providers:")
    if GROQ_AVAILABLE:
        print("   - Groq (llama-3.3-70b-versatile)")
    if GEMINI_AVAILABLE:
        print("   - Gemini (gemini-2.0-flash-exp)")
    print("=" * 60)
    print("")
    
    app.run(host='0.0.0.0', port=5000, debug=True)
