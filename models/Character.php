<?php namespace Void\Character\Models;

use Model;

class Character extends Model {

    protected $table = 'characters';

    public $morphMany = [
        'creators' => ['Void\Creator\Models\Creator', 'type' => 'creation'],
    ];

    public $attachOne = [
        'avatar' => ['System\Models\File'],
        'design_sheet' => ['System\Models\File'],
    ];

    public $attachMany = [
        'supplemental_art' => ['System\Models\File'],
    ];

    /**
     * @var array The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'gender',
        'height',
        'bio',
        'type',
        'status',
    ];
}
