<?php

namespace BFACP\Battlefield;

use BFACP\Adkats\Record;
use Illuminate\Support\Facades\App as App;
use BFACP\Elegant;
use BFACP\Facades\Main as MainHelper;
use BFACP\Repositories\GeoRepository;
use Exception;
use Illuminate\Support\Facades\Cache as Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

/**
 * Class Player.
 */
class Player extends Elegant
{
    /**
     * Should model handle timestamps.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'tbl_playerdata';

    /**
     * Table primary key.
     *
     * @var string
     */
    protected $primaryKey = 'PlayerID';

    /**
     * Fields not allowed to be mass assigned.
     *
     * @var array
     */
    protected $guarded = ['PlayerID'];

    /**
     * Date fields to convert to carbon instances.
     *
     * @var array
     */
    protected $dates = [];

    /**
     * Append custom attributes to output.
     *
     * @var array
     */
    protected $appends = ['profile_url', 'country_flag', 'country_name', 'rank_image', 'links', 'geo', 'link_verified'];

    /**
     * Models to be loaded automatically.
     *
     * @var array
     */
    protected $with = ['game', 'battlelog'];

    /**
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function dogtags()
    {
        return $this->hasMany(\BFACP\Player\Dogtag::class, 'KillerID');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function ban()
    {
        return $this->hasOne(\BFACP\Adkats\Ban::class, 'player_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function stats()
    {
        return $this->hasManyThrough(\BFACP\Player\Stat::class, 'BFACP\Player\Server', 'PlayerID', 'StatsID');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function sessions()
    {
        return $this->hasManyThrough(\BFACP\Player\Session::class, 'BFACP\Player\Server', 'PlayerID', 'StatsID');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function infractionsGlobal()
    {
        return $this->hasOne(\BFACP\Adkats\Infractions\Overall::class, 'player_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function infractionsServer()
    {
        return $this->hasMany(\BFACP\Adkats\Infractions\Server::class, 'player_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function game()
    {
        return $this->belongsTo(\BFACP\Battlefield\Game::class, 'GameID');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function reputation()
    {
        return $this->hasOne(\BFACP\Battlefield\Reputation::class, 'player_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function recordsBy()
    {
        return $this->hasMany(\BFACP\Adkats\Record::class, 'source_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function recordsOn()
    {
        return $this->hasMany(\BFACP\Adkats\Record::class, 'target_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function battlelog()
    {
        return $this->hasOne(\BFACP\Adkats\Battlelog::class, 'player_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function specialGroups()
    {
        return $this->hasMany(\BFACP\Adkats\Special::class, 'player_id');
    }

    /**
     * Does the player have a battlelog persona id linked.
     *
     * @return bool
     */
    public function hasPersona()
    {
        return ! empty($this->battlelog);
    }

    /**
     * Checks if player has a reputation record.
     *
     * @return bool
     */
    public function hasReputation()
    {
        return ! empty($this->reputation);
    }

    /**
     * Purge the cache for the player.
     *
     * @return $this
     */
    public function forget()
    {
        Cache::forget(sprintf('api.player.%u', $this->PlayerID));
        Cache::forget(sprintf('player.%u', $this->PlayerID));

        return $this;
    }

    /**
     * Get the players aliases.
     *
     * @return array
     */
    public function aliases()
    {
        $aliases = Record::where('command_type', 48)->where('target_id',
            $this->PlayerID)->groupBy('record_message')->lists('record_message');

        return $aliases;
    }

    /**
     * Gets the URL to the players profile.
     *
     * @return string
     */
    public function getProfileUrlAttribute()
    {
        return route('player.show', [
            'id'   => $this->PlayerID,
            'name' => $this->SoldierName,
        ]);
    }

    /**
     * Get the country name.
     *
     * @return string
     */
    public function getCountryNameAttribute()
    {
        try {
            if ($this->CountryCode == '--' || empty($this->CountryCode)) {
                throw new Exception();
            }

            $cc = MainHelper::countries($this->CountryCode);

            if ($cc === null) {
                throw new Exception();
            }

            return $cc;
        } catch (Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Get the country image flag.
     *
     * @return string
     */
    public function getCountryFlagAttribute()
    {
        try {
            if ($this->CountryCode == '--' || empty($this->CountryCode)) {
                throw new Exception();
            }

            $path = sprintf('images/flags/24/%s.png', strtoupper($this->CountryCode));

            if (! file_exists(sprintf('%s/%s', public_path(), $path))) {
                throw new Exception();
            }

            return $path;
        } catch (Exception $e) {
            return 'images/flags/24/_unknown.png';
        }
    }

    /**
    * Query Cheat Report for the BF4DB status
    * By H3dius: GPLv3
    */
    private function checkBF4DBoverBF4CheatReport()
    {
        $request = App::make('guzzle')->get(sprintf('http://bf4cheatreport.com/brindex.php?%s',
            http_build_query(
                [
                    'outputtype' => 'json',
                    'cnt'   => 10,
                    'uid' =>  $this->SoldierName,
                ])
            ),
            [
                'connect_timeout' => 5,
                'headers' => [
                    'User-Agent' => 'BFAdminCP',
                    'Accept'     => 'application/json',
                ],
            ]
        );

        $response = $request->json();

        if (isset($response['br_array']) && count($response['br_array']) >= 1) {
            $report = $response['br_array'][0];
            if(!isset($report['db_banned']) || $report['db_banned'] != 1){
                throw new Exception();
            }
        
            $bf4db_profile = [
                'bf4db_api' => false,
                'is_banned' => 1,
                'cheat_score' => 100,
                'url'        => sprintf('https://bf4db.com/player/search?query=%s', $this->SoldierName),
                'reason' => "Banned: " . $report['db_reason'],
            ];
                    
            return $bf4db_profile;
        }
        throw new Exception();
    }
    
    /**
    * Query the BF4DB API. Use BF4CheatReport if the request fails (no persona id)
    * By H3dius: GPLv3
    */
    private function checkBF4DB()
    {
        $token = Config::get('bf4db.key');
        if(is_null($token) || strlen($token) === 0 || is_null($this->battlelog)){
            return $this->checkBF4DBoverBF4CheatReport();
        }
        
        $request = App::make('guzzle')->get(sprintf('https://bf4db.com/api/player/%s?api_token=%s', $this->battlelog->persona_id, $token),
                                            [
                                                'connect_timeout' => 10,
                                                'headers' => [
                                                    'User-Agent' => 'BFAdminCP',
                                                    'Accept' => 'application/json',
                                                    'Content-Type' => 'application/json'
                                                ],
                                            ]
        );

        $response = $request->json()['data'];
        
        // status code to text
        switch($response['is_banned']){
            case -1:
                $reason = 'Not yet reported'; break;
            case 0:
                $reason = 'Under Review'; break;
            case 1:
                $reason = sprintf('Banned: %s', $response['ban_reason']); break;
            case 2:
                $reason = sprintf('Clean: %s', $response['ban_reason']); break;
            case 3:
                $reason = 'Staff Member'; break;
            case 4:
                $reason = sprintf('Glitch: %s', $response['ban_reason']); break;
            case 5:
                $reason = sprintf('Exploit: %s', $response['ban_reason']); break;
        }
                
        // cheat score
        if(in_array($response['is_banned'], [-1, 0, 2, 4, 5])){
            $reason = $reason . sprintf(' - Cheat Score: %s', $response['cheat_score']);
        }
        
        // profile
        $bf4db_profile = [
            'bf4db_api' => true,
            'is_banned' => $response['is_banned'],
            'cheat_score' => $response['cheat_score'],
            'url'        => sprintf('https://bf4db.com/player/%s', $this->battlelog->persona_id),
            'reason' => $reason,
         ];
        return $bf4db_profile;
    }


    /**
     * Generates links to external/internal systems.
     *
     * @return array
     */
     
    public function getLinksAttribute()
    {
        switch ($this->game->Name) {
            case 'BFHL':
                $game = 'BFH';
                break;

            default:
                $game = $this->game->Name;
        }
        

        $links = [];

        // Battlelog URL
        if (is_null($this->battlelog)) {
            $links['battlelog'] = sprintf('https://battlelog.battlefield.com/%s/user/%s', strtolower($game),
                $this->SoldierName);
        } else {
            if ($game == 'BFH') {
                $links['battlelog'] = sprintf('https://battlelog.battlefield.com/%s/agent/%s/stats/%u/pc/',
                    strtolower($game), $this->SoldierName, $this->battlelog->persona_id);
            } else {
                $links['battlelog'] = sprintf('https://battlelog.battlefield.com/%s/soldier/%s/stats/%u/pc/',
                    strtolower($game), $this->SoldierName, $this->battlelog->persona_id);
            }
        }
        
        // custom BF4DB handling
        if ($game == 'BF4') {
            if (is_null($this->battlelog)){
                $url = sprintf('https://bf4db.com/player/search?query=%s', $this->SoldierName);
            } else {
                $url = sprintf('https://bf4db.com/player/%s', $this->battlelog->persona_id);
            }
            try {
                if (Route::currentRouteName() != 'player.show') {
                    throw new Exception();
                }
                
                $bf4db_profile = $this->checkBF4DB();
        
            } catch (Exception $e) {
                $bf4db_profile = [
                    'bf4db_api' => false,
                    'is_banned' => -1,
                    'cheat_score' => 0,
                    'url'        => $url,
                    'reason' => 'OK',
                ];
            
            }
        }


        $links[] = [
            'chatlogs' => route('chatlog.search', ['pid' => $this->PlayerID]),
            'pbbans'   => ! empty($this->PBGUID) ? sprintf('https://www.pbbans.com/mbi-guid-search-%s.html',
                $this->PBGUID) : null,
            'fairplay' => sprintf('https://www.247fairplay.com/CheatDetector/%s', $this->SoldierName),
        ];

        $links2 = [];

        if ($game == 'BF4') {
            $links2 = [
                'aci' => sprintf('https://www.anticheatinc.net/forums/bansearch.php?search=%s&game=bf4&submit=Submit', $this->PBGUID),
                'cheatreport' => sprintf('https://bf4cr.com/?pid=&uid=%s&cnt=200&startdate=', $this->SoldierName),
               /* 'bf4db' => sprintf('https://bf4db.com/player/search?query=%s', $this->SoldierName), */
                'bf4db'    => $game == 'BF4' ? $bf4db_profile : null,
                
                'ba' => sprintf('https://battlefield.agency/player/by-pb_guid/%s', $this->PBGUID),
            ];
        }

        $links = array_merge($links, $links[0], $links2);
        unset($links[0]);

        return $links;
    }

    /**
     * Get the rank image.
     *
     * @return string
     */
    public function getRankImageAttribute()
    {
        switch ($this->game->Name) {
            case 'BF3':
                $rank = $this->GlobalRank;

                if ($rank > 45) {
                    $path = sprintf('images/games/bf3/ranks/large/ss%u.png', $rank - 45);
                } else {
                    $path = sprintf('images/games/bf3/ranks/large/r%u.png', $rank);
                }
                break;

            case 'BF4':
                $path = sprintf('images/games/bf4/ranks/r%u.png', $this->GlobalRank);
                break;

            case 'BFHL':
                $path = sprintf('images/games/bfhl/ranks/r%u.png', $this->GlobalRank);
                break;

            default:
                $path = null;
        }

        return $path;
    }

    /**
     * Gets the geo data from ip address.
     *
     * @return null|array
     */
    public function getGeoAttribute()
    {
        if (empty($this->IP_Address)) {
            return;
        }

        try {
            $geo = app(GeoRepository::class);

            return $geo->set($this->IP_Address)->all();
        } catch (\Exception $e) {
            return;
        }
    }
}
