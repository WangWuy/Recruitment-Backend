<?php

class GeminiController
{
    private $apiKey;
    private $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent';

    public function __construct()
    {
        // Load API key from .env file
        $this->apiKey = $this->loadEnv('GEMINI_API_KEY');

        if (empty($this->apiKey)) {
            error_log('GEMINI_API_KEY not configured in .env file');
        }
    }

    /**
     * Load environment variable from .env file
     */
    private function loadEnv($key)
    {
        $envPath = __DIR__ . '/../.env';
        if (!file_exists($envPath)) {
            return null;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            // Skip comments and empty lines
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            // Parse KEY=VALUE
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $envKey = trim($parts[0]);
                $envValue = trim($parts[1]);

                // Remove quotes if present
                $envValue = trim($envValue, '"\'');

                if ($envKey === $key) {
                    return $envValue;
                }
            }
        }

        return null;
    }

    /**
     * Send a message to Gemini AI and get response
     */
    public function sendMessage()
    {
        try {
            // Check if API key is configured
            if (empty($this->apiKey)) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Gemini API key chưa được cấu hình'
                ]);
                return;
            }

            // Get request body
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['message']) || empty($input['message'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Message is required'
                ]);
                return;
            }

            $message = $input['message'];
            $chatHistory = $input['chatHistory'] ?? [];
            $userContext = $input['userContext'] ?? null;
            $jobContext = $input['jobContext'] ?? null;

            // Build the prompt with context
            $contextMessage = $this->buildContextMessage($message, $userContext, $jobContext);

            // Prepare chat contents with history
            $contents = $this->prepareChatContents($chatHistory, $contextMessage);

            // Call Gemini API
            $response = $this->callGeminiAPI($contents);

            if ($response['success']) {
                echo json_encode([
                    'success' => true,
                    'response' => $response['text']
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => $response['error']
                ]);
            }

        } catch (Exception $e) {
            error_log('GeminiController::sendMessage error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Lỗi khi gọi Gemini API: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get job recommendations based on user profile
     */
    public function getJobRecommendations()
    {
        try {
            if (empty($this->apiKey)) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Gemini API key chưa được cấu hình'
                ]);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['userProfile']) || !isset($input['jobs'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'userProfile and jobs are required'
                ]);
                return;
            }

            $userProfile = $input['userProfile'];
            $jobs = $input['jobs'];

            $prompt = $this->buildJobRecommendationPrompt($userProfile, $jobs);

            $contents = [
                [
                    'parts' => [
                        ['text' => $this->getSystemPrompt()]
                    ]
                ],
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ];

            $response = $this->callGeminiAPI($contents);

            if ($response['success']) {
                echo json_encode([
                    'success' => true,
                    'response' => $response['text']
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => $response['error']
                ]);
            }

        } catch (Exception $e) {
            error_log('GeminiController::getJobRecommendations error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Lỗi khi gọi Gemini API: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get CV improvement suggestions
     */
    public function getCVSuggestions()
    {
        try {
            if (empty($this->apiKey)) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Gemini API key chưa được cấu hình'
                ]);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['cvData'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'cvData is required'
                ]);
                return;
            }

            $cvData = $input['cvData'];
            $prompt = $this->buildCVSuggestionsPrompt($cvData);

            $contents = [
                [
                    'parts' => [
                        ['text' => $this->getSystemPrompt()]
                    ]
                ],
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ];

            $response = $this->callGeminiAPI($contents);

            if ($response['success']) {
                echo json_encode([
                    'success' => true,
                    'response' => $response['text']
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => $response['error']
                ]);
            }

        } catch (Exception $e) {
            error_log('GeminiController::getCVSuggestions error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Lỗi khi gọi Gemini API: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get interview preparation tips
     */
    public function getInterviewPrep()
    {
        try {
            if (empty($this->apiKey)) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Gemini API key chưa được cấu hình'
                ]);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['job'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'job is required'
                ]);
                return;
            }

            $job = $input['job'];
            $prompt = $this->buildInterviewPrepPrompt($job);

            $contents = [
                [
                    'parts' => [
                        ['text' => $this->getSystemPrompt()]
                    ]
                ],
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ];

            $response = $this->callGeminiAPI($contents);

            if ($response['success']) {
                echo json_encode([
                    'success' => true,
                    'response' => $response['text']
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => $response['error']
                ]);
            }

        } catch (Exception $e) {
            error_log('GeminiController::getInterviewPrep error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Lỗi khi gọi Gemini API: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Call Gemini API with retry logic
     */
    private function callGeminiAPI($contents)
    {
        $url = $this->apiUrl . '?key=' . $this->apiKey;

        $data = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 1024,
            ],
            'safetySettings' => [
                [
                    'category' => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ]
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log('Gemini API curl error: ' . $curlError);
            return [
                'success' => false,
                'error' => 'Network error: ' . $curlError
            ];
        }

        $responseData = json_decode($response, true);

        if ($httpCode !== 200) {
            $errorMessage = $responseData['error']['message'] ?? 'Unknown error';
            error_log('Gemini API error (HTTP ' . $httpCode . '): ' . $errorMessage);
            return [
                'success' => false,
                'error' => 'API error: ' . $errorMessage
            ];
        }

        if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
            error_log('Gemini API unexpected response format: ' . $response);
            return [
                'success' => false,
                'error' => 'Unexpected response format'
            ];
        }

        return [
            'success' => true,
            'text' => $responseData['candidates'][0]['content']['parts'][0]['text']
        ];
    }

    /**
     * Build context message with user and job information
     */
    private function buildContextMessage($message, $userContext, $jobContext)
    {
        $contextMessage = "--- Thông tin người dùng ---\n";

        if ($userContext) {
            if (isset($userContext['name'])) {
                $contextMessage .= "Họ tên: {$userContext['name']}\n";
            }
            if (isset($userContext['email'])) {
                $contextMessage .= "Email: {$userContext['email']}\n";
            }
            if (isset($userContext['phone'])) {
                $contextMessage .= "Số điện thoại: {$userContext['phone']}\n";
            }
            if (isset($userContext['skills'])) {
                $contextMessage .= "Kỹ năng: {$userContext['skills']}\n";
            }
            if (isset($userContext['experience'])) {
                $contextMessage .= "Kinh nghiệm: {$userContext['experience']}\n";
            }
        }

        if ($jobContext && !empty($jobContext)) {
            $contextMessage .= "\n--- Công việc liên quan ---\n";
            $count = min(count($jobContext), 3);
            for ($i = 0; $i < $count; $i++) {
                $job = $jobContext[$i];
                $contextMessage .= ($i + 1) . ". {$job['title']} - {$job['company_name']}\n";
                if (isset($job['location'])) {
                    $contextMessage .= "   Địa điểm: {$job['location']}\n";
                }
                if (isset($job['salary_min']) && isset($job['salary_max'])) {
                    $contextMessage .= "   Lương: {$job['salary_min']} - {$job['salary_max']} VNĐ\n";
                }
            }
        }

        $contextMessage .= "\n--- Câu hỏi ---\n";
        $contextMessage .= $message;

        return $contextMessage;
    }

    /**
     * Prepare chat contents with history
     */
    private function prepareChatContents($chatHistory, $currentMessage)
    {
        $contents = [];

        // Add system prompt as first message
        $contents[] = [
            'parts' => [
                ['text' => $this->getSystemPrompt()]
            ]
        ];

        // Add chat history
        foreach ($chatHistory as $msg) {
            $contents[] = [
                'parts' => [
                    ['text' => $msg['text']]
                ]
            ];
        }

        // Add current message
        $contents[] = [
            'parts' => [
                ['text' => $currentMessage]
            ]
        ];

        return $contents;
    }

    /**
     * Get system prompt for AI assistant
     */
    private function getSystemPrompt()
    {
        return <<<EOT
Bạn là một trợ lý AI chuyên về tuyển dụng và phát triển nghề nghiệp tại Việt Nam.
Nhiệm vụ của bạn là:

1. Hỗ trợ người dùng tìm kiếm công việc phù hợp
2. Tư vấn viết CV và thư xin việc
3. Chuẩn bị cho phỏng vấn
4. Tư vấn về lương bổng và đàm phán
5. Hướng dẫn phát triển kỹ năng nghề nghiệp
6. Giải đáp thắc mắc về thị trường lao động

Hãy trả lời một cách:
- Chuyên nghiệp và thân thiện
- Cụ thể và thực tế
- Phù hợp với thị trường Việt Nam
- Ngắn gọn nhưng đầy đủ thông tin
- Sử dụng tiếng Việt

Nếu câu hỏi không liên quan đến nghề nghiệp hoặc tuyển dụng, hãy lịch sự từ chối và đề nghị người dùng hỏi về các chủ đề liên quan đến công việc.
EOT;
    }

    /**
     * Build job recommendation prompt
     */
    private function buildJobRecommendationPrompt($userProfile, $jobs)
    {
        $prompt = "Dựa trên thông tin hồ sơ người dùng và danh sách công việc dưới đây, hãy gợi ý 3 công việc phù hợp nhất và giải thích lý do.\n\n";

        $prompt .= "Thông tin người dùng:\n";
        $prompt .= $this->formatUserProfile($userProfile);
        $prompt .= "\n";

        $prompt .= "Danh sách công việc:\n";
        $prompt .= $this->formatJobList($jobs);
        $prompt .= "\n";

        $prompt .= "Hãy phân tích và đề xuất công việc phù hợp nhất, bao gồm:\n";
        $prompt .= "1. Tên công việc và công ty\n";
        $prompt .= "2. Lý do phù hợp (kỹ năng, kinh nghiệm, mức lương)\n";
        $prompt .= "3. Điểm mạnh của ứng viên cho vị trí này\n";
        $prompt .= "4. Gợi ý cách chuẩn bị để tăng cơ hội được nhận\n";

        return $prompt;
    }

    /**
     * Build CV suggestions prompt
     */
    private function buildCVSuggestionsPrompt($cvData)
    {
        $prompt = "Đánh giá CV sau và đưa ra gợi ý cải thiện cụ thể:\n\n";
        $prompt .= $this->formatCVData($cvData);
        $prompt .= "\n";
        $prompt .= "Hãy đánh giá và đưa ra:\n";
        $prompt .= "1. Điểm mạnh của CV\n";
        $prompt .= "2. Điểm cần cải thiện\n";
        $prompt .= "3. Gợi ý cụ thể để CV trở nên hấp dẫn hơn với nhà tuyển dụng\n";
        $prompt .= "4. Các từ khóa nên bổ sung\n";

        return $prompt;
    }

    /**
     * Build interview preparation prompt
     */
    private function buildInterviewPrepPrompt($job)
    {
        $prompt = "Tôi sắp có buổi phỏng vấn cho vị trí: {$job['title']} tại {$job['company_name']}.\n\n";

        $prompt .= "Mô tả công việc:\n";
        $prompt .= ($job['description'] ?? 'Không có mô tả') . "\n\n";

        $prompt .= "Yêu cầu:\n";
        $prompt .= ($job['requirements'] ?? 'Không có yêu cầu') . "\n\n";

        $prompt .= "Hãy giúp tôi chuẩn bị:\n";
        $prompt .= "1. Các câu hỏi phỏng vấn có thể gặp\n";
        $prompt .= "2. Cách trả lời hiệu quả\n";
        $prompt .= "3. Những điểm cần nhấn mạnh\n";
        $prompt .= "4. Trang phục và thái độ phù hợp\n";
        $prompt .= "5. Câu hỏi nên hỏi nhà tuyển dụng\n";

        return $prompt;
    }

    /**
     * Format user profile for prompt
     */
    private function formatUserProfile($profile)
    {
        $formatted = "";
        $formatted .= "Họ tên: " . ($profile['name'] ?? 'Chưa cập nhật') . "\n";
        $formatted .= "Email: " . ($profile['email'] ?? 'Chưa cập nhật') . "\n";
        $formatted .= "Điện thoại: " . ($profile['phone'] ?? 'Chưa cập nhật') . "\n";
        $formatted .= "Kỹ năng: " . ($profile['skills'] ?? 'Chưa cập nhật') . "\n";
        $formatted .= "Kinh nghiệm: " . ($profile['experience'] ?? 'Chưa cập nhật') . "\n";
        $formatted .= "Học vấn: " . ($profile['education'] ?? 'Chưa cập nhật') . "\n";
        return $formatted;
    }

    /**
     * Format job list for prompt
     */
    private function formatJobList($jobs)
    {
        $formatted = "";
        foreach ($jobs as $i => $job) {
            $formatted .= ($i + 1) . ". {$job['title']} - {$job['company_name']}\n";
            $formatted .= "   Địa điểm: " . ($job['location'] ?? 'N/A') . "\n";
            if (isset($job['salary_min'])) {
                $formatted .= "   Lương: {$job['salary_min']} - {$job['salary_max']} VNĐ\n";
            }
            $formatted .= "   Mô tả: " . ($job['description'] ?? 'N/A') . "\n\n";
        }
        return $formatted;
    }

    /**
     * Format CV data for prompt
     */
    private function formatCVData($cvData)
    {
        $formatted = "Thông tin cá nhân:\n";
        $formatted .= "- Họ tên: " . ($cvData['fullName'] ?? 'N/A') . "\n";
        $formatted .= "- Email: " . ($cvData['email'] ?? 'N/A') . "\n";
        $formatted .= "- Điện thoại: " . ($cvData['phone'] ?? 'N/A') . "\n\n";

        $formatted .= "Mục tiêu:\n";
        $formatted .= ($cvData['summary'] ?? 'Chưa có') . "\n\n";

        if (isset($cvData['experience'])) {
            $formatted .= "Kinh nghiệm:\n";
            foreach ($cvData['experience'] as $exp) {
                $formatted .= "- {$exp['position']} tại {$exp['company']}\n";
            }
            $formatted .= "\n";
        }

        if (isset($cvData['education'])) {
            $formatted .= "Học vấn:\n";
            foreach ($cvData['education'] as $edu) {
                $formatted .= "- {$edu['degree']} - {$edu['institution']}\n";
            }
            $formatted .= "\n";
        }

        if (isset($cvData['skills'])) {
            $formatted .= "Kỹ năng:\n";
            $formatted .= implode(', ', $cvData['skills']) . "\n";
        }

        return $formatted;
    }
}
