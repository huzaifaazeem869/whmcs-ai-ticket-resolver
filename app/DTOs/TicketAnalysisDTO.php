<?php

declare(strict_types=1);

namespace App\DTOs;

use InvalidArgumentException;

readonly class TicketAnalysisDTO
{
    public function __construct(
        public string $reasoning,
        public string $matchedKbArticle, // <-- Nayi Field
        public string $department,
        public string $priority,
        public bool $isInvoiceMentioned,
        public string $suggestedReply,
        public string $customerMood,
    ) {}

    public static function fromArray(array $data): self
    {
        if (! isset(
            $data['reasoning'],
            $data['matched_kb_article'],
            $data['department'],
            $data['priority'],
            $data['is_invoice_mentioned'],
            $data['suggested_reply'],
            $data['customer_mood']
        )) {
            throw new InvalidArgumentException('Gemini response is missing required schema fields.');
        }

        return new self(
            reasoning: (string) $data['reasoning'],
            matchedKbArticle: (string) $data['matched_kb_article'],
            department: (string) $data['department'],
            priority: (string) $data['priority'],
            isInvoiceMentioned: (bool) $data['is_invoice_mentioned'],
            suggestedReply: (string) $data['suggested_reply'],
            customerMood: (string) $data['customer_mood'],
        );
    }

    /**
     * Convert DTO to associative array for API responses and WHMCS storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'reasoning'            => $this->reasoning,
            'matched_kb_article'   => $this->matchedKbArticle,
            'department'           => $this->department,
            'priority'             => $this->priority,
            'is_invoice_mentioned' => $this->isInvoiceMentioned,
            'suggested_reply'      => $this->suggestedReply,
            'customer_mood'        => $this->customerMood,
        ];
    }
}