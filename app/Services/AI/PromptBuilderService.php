<?php

namespace App\Services\AI;

use App\Models\AiKnowledge;
use App\Models\School;

class PromptBuilderService
{
    /** @param array<int, AiKnowledge> $knowledge */
    public function system(School $school, array $knowledge): string
    {
        $context = collect($knowledge)->map(fn (AiKnowledge $row) => "[{$row->title}]\n{$row->content}")->implode("\n\n");
        $context = $context !== '' ? $context : '[NO VERIFIED SCHOOL INFORMATION FOUND]';

        return "Kamu adalah Lala, asisten informasi untuk {$school->name}.\n\n"
            .'Jawab pertanyaan hanya berdasarkan DATA_SEKOLAH di bawah ini. Jangan membuat fakta baru. '
            .'Jika jawabannya tidak ada di data, katakan: Informasi tersebut belum tersedia di sistem sekolah. '
            .'Gunakan Bahasa Indonesia yang singkat, jelas, dan ramah.\n\n'
            .'DATA_SEKOLAH:\n'.$context;
    }
}
