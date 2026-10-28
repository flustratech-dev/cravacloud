<?php

namespace App\Services;

/**
 * Fluent builder untuk pesan notifikasi WhatsApp.
 *
 * Mendukung pesan teks murni maupun lampiran berkas dokumen (PDF) / gambar
 * dengan caption. Digunakan oleh Laravel Notification via WhatsAppChannel.
 */
class WhatsAppMessage
{
    protected ?string $content = null;
    protected mixed $mediaContent = null;
    protected ?string $mediaFilename = null;
    protected string $mediaType = 'document';
    protected ?string $mediaCaption = null;
    protected ?string $sessionId = null;

    public static function create(?string $content = null): self
    {
        $instance = new self();
        if ($content !== null) {
            $instance->content($content);
        }

        return $instance;
    }

    public function content(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function document(mixed $fileContent, string $filename, ?string $caption = null): self
    {
        $this->mediaContent = $fileContent;
        $this->mediaFilename = $filename;
        $this->mediaType = 'document';
        $this->mediaCaption = $caption;

        return $this;
    }

    public function image(mixed $fileContent, string $filename, ?string $caption = null): self
    {
        $this->mediaContent = $fileContent;
        $this->mediaFilename = $filename;
        $this->mediaType = 'image';
        $this->mediaCaption = $caption;

        return $this;
    }

    public function session(?string $sessionId): self
    {
        $this->sessionId = $sessionId;

        return $this;
    }

    public function hasMedia(): bool
    {
        return ! empty($this->mediaContent) && ! empty($this->mediaFilename);
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * Kirim pesan ke nomor yang ditentukan.
     */
    public function sendTo(string $phone): bool
    {
        if ($this->hasMedia()) {
            return WhatsAppGateway::sendMedia(
                phone: $phone,
                fileContent: $this->mediaContent,
                filename: $this->mediaFilename,
                type: $this->mediaType,
                caption: $this->mediaCaption ?? $this->content,
                sessionId: $this->sessionId
            );
        }

        if ($this->content !== null && trim($this->content) !== '') {
            return WhatsAppGateway::send(
                phone: $phone,
                message: $this->content,
                sessionId: $this->sessionId
            );
        }

        return false;
    }
}
