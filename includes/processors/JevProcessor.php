<?php
/**
 * AI Central System - Jev Processor (TypeSafe System One)
 *
 * Jev is a decision model, not a text writer: it takes a state (text or JSON) and typed questions and answers each
 * with calibrated probabilities. Question types (docs: https://docs.typesafe.ai):
 *   choice: 'criteria' = {option: description}  -> answer: choice, probabilities {option: p}, confidence
 *   score:  'criteria' = [level descriptions]   -> answer: score (level index), probabilities, legend, confidence
 *   noul:   yes/no (optional criteria {true, false}) -> answer: noul (probability of yes)
 * Each question: {"type": ..., "instructions": ..., "criteria": ...}. Many questions against one state in one call is
 * far cheaper and faster than one call each.
 *
 * Apps call it through ai_makeDecision() (ai_functions.php), which passes options 'state' and 'questions'.
 * The reply's 'response' is the answers as JSON; 'answers' is the same, decoded.
 *
 * @version 1.0.0
 * @date 2026-11-05
 */

require_once __DIR__ . '/BaseProcessor.php';
require_once __DIR__ . '/../../common/common_ai.php';

class JevProcessor implements BaseProcessor {

    /** Request body: the state (options 'state', else the prompt), the model, the questions (options 'questions'). */
    public function buildRequest($prompt, $options) {
        if (empty($options['questions']) || !is_array($options['questions'])) {
            throw new Exception('Jev needs questions (options questions: {id: {type, instructions, criteria}})');
        }
        return [
            'state' => $options['state'] ?? $prompt,
            'model' => $options['model_code'] ?? 'jev-latest',
            'questions' => (object)$options['questions'],
        ];
    }

    public function parseResponse($apiResponse, $httpCode, $originalRequest = null) {
        $apiResponse = is_array($apiResponse) ? $apiResponse : [];
        if ($httpCode !== 200 || !isset($apiResponse['answers']) || !is_array($apiResponse['answers'])) {
            $err = $apiResponse['error']['message'] ?? $apiResponse['detail'] ?? $apiResponse['error'] ?? "HTTP $httpCode error";
            $err = is_string($err) ? $err : json_encode($err);
            aiCentral_logMessage("JevProcessor: API error - $err", 'ERROR');
            return ['success' => false, 'error' => $err, 'error_type' => 'http_error', 'raw_response_data' => $apiResponse];
        }
        $usage = $apiResponse['usage'] ?? [];
        return [
            'success' => true,
            'response' => json_encode($apiResponse['answers']),
            'answers' => $apiResponse['answers'],
            'usage' => [
                'input_tokens' => (int)($usage['input_tokens'] ?? 0),
                'output_tokens' => (int)($usage['output_tokens'] ?? 0),
                'thinking_tokens' => 0,
            ],
            'tool_calls' => [],
            'stop_reason' => 'stop',
            'message_id' => null,
            'raw_response_data' => $apiResponse,
        ];
    }

    /** No tools. */
    public function buildTools($capabilities, $providerCode = null) {
        return null;
    }

    public function getEndpoint($modelCode, $providerCode = null) {
        return 'https://api.typesafe.ai/v1/systemone';
    }

    public function getHeaders($apiKey, $providerCode = null) {
        return [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ];
    }
}
