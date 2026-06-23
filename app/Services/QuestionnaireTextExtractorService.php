<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use ZipArchive;

class QuestionnaireTextExtractorService
{
    public function extract(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return match ($extension) {
            'txt' => $this->extractTextFile($file),
            'docx' => $this->extractDocx($file),
            'pdf' => throw ValidationException::withMessages([
                'file' => 'PDF questionnaire uploads are coming soon. Please upload a .txt or .docx file for now.',
            ]),
            default => throw ValidationException::withMessages([
                'file' => 'Only .txt and .docx questionnaire files are supported.',
            ]),
        };
    }

    private function extractTextFile(UploadedFile $file): string
    {
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw new RuntimeException('The uploaded text file could not be read.');
        }

        return $this->normalise($contents);
    }

    private function extractDocx(UploadedFile $file): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('DOCX extraction requires the PHP Zip extension to be enabled.');
        }

        $zip = new ZipArchive;

        if ($zip->open($file->getRealPath()) !== true) {
            throw new RuntimeException('The uploaded DOCX file could not be opened.');
        }

        $document = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($document === false) {
            throw new RuntimeException('The uploaded DOCX file does not contain readable document text.');
        }

        $document = str_replace(['</w:p>', '</w:tr>', '<w:br/>', '<w:br />'], "\n", $document);
        $document = preg_replace('/<w:tab\s*\/>/i', "\t", $document) ?? $document;
        $text = html_entity_decode(strip_tags($document), ENT_QUOTES | ENT_XML1, 'UTF-8');

        return $this->normalise($text);
    }

    private function normalise(string $text): string
    {
        $text = preg_replace('/\r\n?|\x{2028}|\x{2029}/u', "\n", $text) ?? $text;
        $text = preg_replace('/[\x{00A0}\t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/[ ]+\n/u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
