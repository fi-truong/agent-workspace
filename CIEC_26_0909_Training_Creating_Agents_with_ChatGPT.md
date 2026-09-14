# TRAINING MODULE
## Creating AI Agents with ChatGPT

**AI+ Training Program — Lawrence S. Ting School**

*CIEC — Center of Innovation, Entrepreneurship and Creativity*

---

## 1. Introduction / Giới thiệu

This training module is designed for LSTS staff to learn how to create and use AI Agents with ChatGPT. By the end of this module, you will be able to:

1. Understand what an AI Agent is and how it works
2. Write effective system prompts to customize Agent behavior
3. Create practical agents for school-specific tasks
4. Apply best practices for safe and effective AI use

*Mô-đun đào tạo này được thiết kế cho nhân viên LSTS để học cách tạo và sử dụng AI Agents với ChatGPT. Sau khi hoàn thành, bạn sẽ có thể:*

- Hiểu AI Agent là gì và cách hoạt động
- Viết system prompt hiệu quả để tùy chỉnh hành vi Agent
- Tạo agent thực tế cho các tác vụ cụ thể trong trường
- Áp dụng best practices để sử dụng AI an toàn và hiệu quả

---

## 2. What is an AI Agent? / AI Agent là gì?

An **AI Agent** is a customized version of ChatGPT that has been given specific instructions (a **system prompt**) that defines its personality, knowledge, behavior, and output style.

*Think of it like hiring a specialist: instead of a general assistant, you create an expert in a specific domain who follows your rules.*

### 2.1 Key Components of an Agent / Các thành phần chính

| Component | Description (EN) | Mô tả (VI) |
|-----------|------------------|------------|
| **System Prompt** | Instructions that define how the Agent behaves, what it knows, and how it responds | Hướng dẫn xác định cách Agent hành xử, những gì nó biết, và cách nó phản hồi |
| **Knowledge Base** | Documents or data the Agent can reference (in ChatGPT: uploaded files) | Tài liệu hoặc dữ liệu Agent có thể tham khảo (trong ChatGPT: file upload) |
| **Conversation** | The back-and-forth between user and Agent; each message builds context | Cuộc hội thoại giữa người dùng và Agent; mỗi tin nhắn thêm ngữ cảnh |

### 2.2 Why Create Custom Agents? / Tại sao nên tạo Agent?

1. **Consistency**: Same task, same quality, every time
2. **Efficiency**: No need to re-explain context each time
3. **Quality**: Pre-defined rules ensure professional outputs
4. **Sharing**: Team members can use the same agent for consistent results

---

## 3. Writing Effective System Prompts / Viết System Prompt hiệu quả

The system prompt is the brain of your Agent. A well-written prompt includes these elements:

### 3.1 The R.S.C.O. Framework

| Element | What to Define | Example |
|---------|----------------|---------|
| **R - Role** | Who is the Agent? What expertise does it have? | *"You are an experienced IB Mathematics teacher with 10 years of experience..."* |
| **S - Style** | Tone, language level, formality | *"Use clear, encouraging language suitable for high school students. Respond in Vietnamese unless asked otherwise."* |
| **C - Constraints** | What should the Agent NOT do? Boundaries? | *"Never give direct answers to homework problems. Instead, guide students through the solution process with hints."* |
| **O - Output** | Format, structure, length of response | *"Format responses as: 1) Concept explanation, 2) Worked example, 3) Practice problem. Keep each section under 150 words."* |

### 3.2 Example System Prompt (Full)

```
You are an experienced IB Mathematics teacher at Lawrence S. Ting School, specializing in IB Math AA (Analysis and Approaches) for both Standard Level and Higher Level students.

YOUR ROLE:
Help students understand mathematical concepts through guided discovery. Do NOT give direct answers to homework or exam questions. Instead, ask clarifying questions, provide hints, and guide students to discover solutions themselves.

STYLE:
- Use clear, encouraging language
- Respond primarily in Vietnamese, but use English for mathematical terms
- Adapt your explanation level based on student responses
- Celebrate small wins to build confidence

OUTPUT FORMAT:
1. Acknowledge the student's question
2. Ask 1-2 clarifying questions if needed
3. Provide a hint or related example
4. Ask the student what they think the next step might be
```

---

## 4. Practical Examples for LSTS / Ví dụ thực tế cho LSTS

### 4.1 Math Quiz Generator (Tạo đề kiểm tra Toán)

**Use case**: Teachers need to quickly generate practice quizzes for different topics and difficulty levels.

**System Prompt excerpt:**
```
You are a math quiz generator for grades 6-12. When given a topic and difficulty level, create a quiz with:
- 5 multiple choice questions (4 options each)
- 3 short answer questions
- 1 extended response question

Include an answer key at the end. Questions should follow Vietnamese Ministry of Education standards and IB curriculum where applicable.
```

---

### 4.2 Parent Communication Assistant (Trợ lý liên lạc Phụ huynh)

**Use case**: Draft professional emails to parents in Vietnamese or English with appropriate tone.

**System Prompt excerpt:**
```
You are a parent communication assistant for LSTS teachers. Help draft emails that are:
- Professional yet warm in tone
- Clear and concise
- Solution-focused

Always ask for: student name, subject/class, topic of communication, preferred language (Vietnamese/English/bilingual), and desired outcome before drafting.
```

---

### 4.3 Essay Feedback Assistant (Trợ lý phản hồi bài luận)

**Use case**: Provide structured feedback on student essays following IB criteria.

**System Prompt excerpt:**
```
You are an IB Extended Essay supervisor. When given a student essay excerpt, provide feedback in this format:

1. STRENGTHS: What the student did well (2-3 points)
2. AREAS FOR IMPROVEMENT: Specific suggestions (2-3 points)
3. QUESTIONS TO CONSIDER: Prompts to help the student reflect
4. NEXT STEPS: Actionable advice for revision

Use the IB assessment criteria as your reference. Be constructive and encouraging.
```

---

## 5. Step-by-Step: Creating an Agent in ChatGPT

Follow these steps to create your own Agent:

### Step 1: Access ChatGPT
- Log in to your ChatGPT account (Plus/Team/Enterprise)
- Click "Explore GPTs" or "Create" in the sidebar

### Step 2: Define Your Agent
- Give your Agent a clear name (e.g., "LSTS Math Helper")
- Write a brief description of what it does

### Step 3: Write the System Prompt
- Use the R.S.C.O. framework from Section 3
- Be specific about role, style, constraints, and output format
- Include examples of ideal responses if possible

### Step 4: Add Knowledge (Optional)
- Upload relevant documents (curriculum guides, rubrics, policies)
- Enable web browsing if current information is needed

### Step 5: Test and Refine
- Test with various inputs to check if it behaves as expected
- Refine the system prompt based on unexpected behaviors
- Save and share with colleagues if appropriate

### Step 6: Save and Use
- Save your Agent
- Access it anytime from your GPTs list
- Share via link if your team needs access

---

## 6. Best Practices & Safety / Best Practices & An toàn

### 6.1 DO / NÊN ✓

- Be specific in your instructions — vague prompts lead to inconsistent results
- Test your Agent with multiple scenarios before sharing
- Iterate — your first version won't be perfect, and that's okay
- Share successful Agents with your team
- Keep a copy of your system prompt for future reference

### 6.2 DON'T / KHÔNG NÊN ✗

- Upload sensitive student data (names, grades, personal information)
- Share your ChatGPT credentials with others
- Rely on AI for final decisions — always review outputs before using
- Assume AI outputs are always correct — verify important information
- Use AI to generate content without reviewing for accuracy and appropriateness

### 6.3 Data Privacy Reminder / Nhắc nhở bảo mật dữ liệu

> ⚠️ **IMPORTANT**: Never input student names, ID numbers, health information, or other personally identifiable information (PII) into ChatGPT or any AI system. If you're unsure whether data is sensitive, assume it is and consult with IT or CIEC before proceeding.

---

## 7. Hands-on Exercise / Bài tập thực hành

Complete this exercise during the offline workshop session:

### Exercise: Create Your First Agent

**Task**: Create an Agent that helps with a task relevant to your department.

#### Step 1: Identify a Need (5 minutes)
Think of a repetitive task in your daily work that could benefit from AI assistance. Examples:
- Drafting routine emails
- Creating lesson plan templates
- Generating quiz questions
- Summarizing meeting notes

#### Step 2: Draft Your System Prompt (10 minutes)
Using the R.S.C.O. framework, write a system prompt for your Agent. Consider:
- What role should it play?
- What style/tone should it use?
- What should it NOT do?
- What format should outputs follow?

#### Step 3: Build and Test (15 minutes)
- Create your Agent in ChatGPT
- Test it with 3-5 different inputs
- Note what works well and what needs improvement

#### Step 4: Refine and Share (10 minutes)
- Update your system prompt based on testing
- Share your Agent with a partner for feedback
- Discuss: What worked? What surprised you? What would you change?

---

## 8. Additional Resources / Tài liệu tham khảo

### 8.1 Internal Resources
- **AI+ Portal**: lsts.edu.vn/ai-plus (Prompt Library, Agent Templates, Sharing Showcase)
- **AI Policy & Guidelines**: Available on AI+ Portal
- **Support**: Submit tickets through AI+ Portal → Support

### 8.2 External Resources
- **OpenAI Documentation**: platform.openai.com/docs
- **Prompt Engineering Guide**: promptingguide.ai
- **IB Resources**: ibo.org (for curriculum-specific guidance)

### 8.3 Contact

For questions about AI+ or this training module:

**CIEC — Center of Innovation, Entrepreneurship and Creativity**
Lawrence S. Ting School
Email: ciec@lsts.edu.vn

---

*© 2026 CIEC — Lawrence S. Ting School | AI+ Training Program*
