<?php

namespace BFACP\Account;

use BFACP\Elegant;

/**
 * Class Game.
 */
class DiscordVeriToken extends Elegant
{
    /**
     * Should model handle timestamps.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'tbl_discordveritokens';

    /**
     * Table primary key.
     *
     * @var string
     */
    protected $primaryKey = 'PlayerID';

    /**
     * Fields allowed to be mass assigned.
     *
     * @var array
     */
    protected $guarded = ['PlayerID', 'Token', 'DiscordID'];

    /**
     * Date fields to convert to carbon instances.
     *
     * @var array
     */
    protected $dates = [];

    /**
     * The attributes excluded form the models JSON response.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * Append custom attributes to output.
     *
     * @var array
     */
    protected $appends = [];

    /**
     * Models to be loaded automatically.
     *
     * @var array
     */
    protected $with = [];

    /**
     * @return Model
     */
    public function player()
    {
        return $this->belongsTo(\BFACP\Battlefield\Player::class, 'player_id');
    }
}