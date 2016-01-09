<?php

/**
 * Biblioteka implementująca BotAPI GG http://boty.gg.pl/
 * Copyright (C) 2013 GG Network S.A. Marcin Bagiński <marcin.baginski@firma.gg.pl>
 * Copyright (C) 2015 Lech Groblewicz <xrogers@gmail.com>.
 *
 * This library is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see<http://www.gnu.org/licenses/>.
 */

namespace GGBotApi;

/**
 * Pomocnicza klasa do autoryzacji przez HTTP.
 */
class BotAPIAuthorization
{
    private $data = [
        'token' => null,
        'server' => null,
        'port' => null,
    ];
    private $isValid;

    /**
     * @return bool true jeśli autoryzacja przebiegła prawidłowo
     */
    public function isAuthorized()
    {
        return $this->isValid;
    }

    public function __construct($ggid, $userName, $password)
    {
        $this->isValid = $this->getData($ggid, $userName, $password);
    }

    private function getData($ggid, $userName, $password)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://botapi.gadu-gadu.pl/botmaster/getToken/'.$ggid,
            CURLOPT_USERPWD => $userName.':'.$password,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_VERBOSE => false,
            CURLOPT_HTTPHEADER => ['BotApi-Version: '.PushConnection::BOTAPI_VERSION],
        ]);

        $xml = curl_exec($ch);
        curl_close($ch);

        $match1 = preg_match('/<token>(.+?)<\/token>/', $xml, $tmpToken);
        $match2 = preg_match('/<server>(.+?)<\/server>/', $xml, $tmpServer);
        $match3 = preg_match('/<port>(.+?)<\/port>/', $xml, $tmpPort);

        if (!($match1 && $match2 && $match3)) {
            return false;
        }

        $this->data['token'] = $tmpToken[1];
        $this->data['server'] = $tmpServer[1];
        $this->data['port'] = $tmpPort[1];

        return true;
    }

    /**
     * Pobiera aktywny token, port i adres BotMastera.
     *
     * @return bool false w przypadku błędu
     */
    public function getServerAndToken()
    {
        return $this->data;
    }
}
