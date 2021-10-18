<?php

namespace BFACP\Http\Controllers\Api;

use Illuminate\Http\Request;

use BFACP\Http\Controllers\Controller as BaseController;
use BFACP\Account\DiscordVeriToken;
use BFACP\Battlefield\Player;
use BFACP\Helpers\Main;

class AutomationController extends BaseController
{
    /**
     * Show the profile for the given user.
     *
     * @param  String  discordUID
     * @param  String  soldierName
     * @return Response
     */
    public function discordLink($discordUID, $soldierName, $token)
    {
        if ($token != env('EXAPI_TOKEN') || !$token) return response()->json([ 'status' => 401,
                                                                               'message' => 'Unauthorized',
                                                                               'error' => 'You\'re unauthorized to perform this action.' ]);

        $emptyvar = (!$discordUID) ? 'discordUID' : ((!$soldierName) ? 'soldierName' : NULL);
        if (!$discordUID || !$soldierName) return response()->json([ 'status' => 400,
                                                                     'message' => 'Bad Request',
                                                                     'error' => 'The parameter `'.$emptyvar.'` is null or empty.' ]);

        //Check for SoldierName thru tbl_playerdata
            //If no player -> return json error
            //Else -> go thru

        //Get the Player Model
        //Add tbl_discordcodes entry for playerID
        //Add discordUID, linkVerified=false to Player Model and save

        //Return response with code

        $found = Player::where('SoldierName', $soldierName)->where('GameID', 1)->first();

        if (!$found) return response()->json([ 'status' => 404,
                                               'message' => 'Not Found',
                                               'error' => 'The specified soldierName `'.$soldierName.'` was not found in the database.' ]);

        $found->LinkVerified = false;
        $found->save();
        
        $util = new Main;
        $salt = $util->generateStrongPassword(6, false, 'd');

        $token = new DiscordVeriToken;
        $token->PlayerID = $found->PlayerID;
        $token->Token = $salt;
        $token->DiscordID = $discordUID;
        $token->save();

        return response()->json([ 'status' => 200,
                                  'message' => 'OK',
                                  'verification' => $salt ]);
    }
}