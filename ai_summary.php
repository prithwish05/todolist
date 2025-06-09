<?php
// ai_summary.php
// Disable display of errors for JSON endpoints
ini_set("display_errors", "0");
error_reporting(E_ALL);
ini_set("log_errors", "1");
ini_set("error_log", __DIR__ . "/php-error.log");

// TaskSummarizer class
class TaskSummarizer {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function generateDailySummary($email) {
        $completedTasks = $this->getTodaysCompletedTasks($email);
        if (empty($completedTasks)) {
            return "No tasks completed today.";
        }

        $summary = $this->getAISummary($completedTasks);
        $this->saveSummary($email, $summary);
        return $summary;
    }

    private function getTodaysCompletedTasks($email) {
        $today = date("Y-m-d");
        $stmt = $this->conn->prepare("SELECT title, description FROM tasks WHERE email = ? AND DATE(completed_date) = ? AND status = 'completed'");
        $stmt->bind_param("ss", $email, $today);
        $stmt->execute();
        $result = $stmt->get_result();

        $tasks = [];
        while ($row = $result->fetch_assoc()) {
            $tasks[] = $row;
        }
        $stmt->close();
        return $tasks;
    }

    private function getAISummary($tasks) {
        $apiKey = getenv("OPENAI_API_KEY");
        if (!$apiKey) throw new Exception("OpenAI API key not found in environment variables.");

        $prompt = "Summarize these tasks:\n\n";
        foreach ($tasks as $task) {
            $prompt .= "- " . $task['title'] . ": " . $task['description'] . "\n";
        }

        // cURL to OpenAI API
        $ch = curl_init("https://api.openai.com/v1/chat/completions");
        $headers = [
            "Content-Type: application/json",
            "Authorization: Bearer $apiKey"
        ];
        $data = json_encode([
            "model" => "gpt-3.5-turbo",
            "messages" => [
                ["role" => "system", "content" => "You are a task summarizer assistant."],
                ["role" => "user", "content" => $prompt]
            ]
        ]);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception("cURL error: " . curl_error($ch));
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode !== 200) {
            throw new Exception("OpenAI API request failed with status code $statusCode: $response");
        }

        $result = json_decode($response, true);
        if (!isset($result["choices"][0]["message"]["content"])) {
            throw new Exception("Invalid response from OpenAI: $response");
        }

        return trim($result["choices"][0]["message"]["content"]);
    }

    private function saveSummary($email, $summary) {
        $stmt = $this->conn->prepare("INSERT INTO summaries (email, summary, date) VALUES (?, ?, NOW())");
        $stmt->bind_param("ss", $email, $summary);
        $stmt->execute();
        $stmt->close();
    }
}
?>
