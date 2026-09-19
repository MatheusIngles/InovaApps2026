<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** Histórico do chat. Sempre filtrado pela empresa ativa (BelongsToCompany) e, na tela, pelo usuário. */
class ChatMessage extends Model
{
    use BelongsToCompany;

    protected $guarded = [];
}
