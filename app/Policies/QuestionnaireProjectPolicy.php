<?php

namespace App\Policies;

use App\Models\QuestionnaireProject;
use App\Models\User;

class QuestionnaireProjectPolicy
{
    public function view(User $user, QuestionnaireProject $project): bool
    {
        return $user->id === $project->user_id;
    }

    public function update(User $user, QuestionnaireProject $project): bool
    {
        return $user->id === $project->user_id;
    }

    public function delete(User $user, QuestionnaireProject $project): bool
    {
        return $user->id === $project->user_id;
    }
}
