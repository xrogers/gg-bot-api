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
 * @brief Klasa reprezentująca połączenie PUSH z BotMasterem.
 * Autoryzuje połączenie w trakcie tworzenia i wysyła wiadomości do BotMastera.
 */
class PushConnection
{
    const BOTAPI_VERSION = 'GGBotApi-2.4-PHP';

    const STATUS_AWAY = 'away';
    const STATUS_FFC = 'ffc';
    const STATUS_BACK = 'back';
    const STATUS_DND = 'dnd';
    const STATUS_INVISIBLE = 'invisible';

    /** @var BotAPIAuthorization */
    private $authorization;
    /** @var int */
    private $gg;

    /**
     * Konstruktor PushConnection.
     *
     * @param int                 $ggNumber
     * @param BotAPIAuthorization $apiAuthorization
     */
    public function __construct($ggNumber, BotAPIAuthorization $apiAuthorization)
    {
        $this->gg = $ggNumber;
        $this->authorization = $apiAuthorization;
    }

    public function pushOne(MessageBuilder $message)
    {
        return $this->push([$message]);
    }

    /**
     * Wysyła wiadomość (obiekt lub tablicę obiektów MessageBuilder) do BotMastera.
     *
     * @param MessageBuilder[] $messages tablica obiektów MessageBuilder
     *
     * @return bool|int
     *
     * @throws \Exception
     */
    public function push(array $messages)
    {
        if (!$this->authorization->isAuthorized()) {
            return false;
        }

        $count = 0;

        $data = $this->authorization->getServerAndToken();
        foreach ($messages as $message) {
            foreach ($message->getImages() as $hash => $fileContent) {
                if (!$this->existsImage($hash)) {
                    if (!$this->putImage($fileContent)) {
                        throw new \Exception('Nie udało się wysłać obrazka '.$hash);
                    }
                }
            }

            $ch = $this->getSingleCurlHandle();

            curl_setopt($ch, CURLOPT_HTTPHEADER, ['BotApi-Version: '.self::BOTAPI_VERSION, 'Token: '.$data['token'], 'Send-to-offline: '.(($message->sendToOffline) ? '1' : '0')]);
            curl_setopt($ch, CURLOPT_URL, 'https://'.$data['server'].'/sendMessage/'.$this->gg);
            curl_setopt($ch, CURLOPT_POSTFIELDS, 'to='.implode(',', $message->recipientNumbers).'&msg='.urlencode($message->getProtocolMessage()));

            $r = curl_exec($ch);
            curl_close($ch);

            $count += (strpos($r, '<result><status>0</status></result>') !== false);
        }

        return $count;
    }

	/**
	 * Ustawia opis botowi.
	 *
	 * @param string $statusDescription Treść opisu
	 * @param string $status Typ opisu
	 * @param null $graphic Nieużywane. Zostaje dla kompatybilności
	 * @return bool
	 */
    public function setStatus($statusDescription, $status = '', $graphic = null)
    {
        $this->checkAuthorization();

        $statusDescription = urlencode($statusDescription);

        switch ($status) {
            case self::STATUS_AWAY:
                $h = ((empty($statusDescription)) ? 3 : 5);
                break;
            case self::STATUS_FFC:
                $h = ((empty($statusDescription)) ? 23 : 24);
                break;
            case self::STATUS_BACK:
                $h = ((empty($statusDescription)) ? 2 : 4);
                break;
            case self::STATUS_DND:
                $h = ((empty($statusDescription)) ? 33 : 34);
                break;
            case self::STATUS_INVISIBLE:
                $h = ((empty($statusDescription)) ? 20 : 22);
                break;
            default:
                $h = 0;
                break;
        }

        $data = $this->authorization->getServerAndToken();

        $ch = $this->getSingleCurlHandle();
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['BotApi-Version: '.self::BOTAPI_VERSION, 'Token: '.$data['token']]);
        curl_setopt($ch, CURLOPT_URL, 'https://'.$data['server'].'/setStatus/'.$this->gg);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'status='.$h.'&desc='.$statusDescription);

        $r = curl_exec($ch);
        curl_close($ch);

        return strpos($r, '<result><status>0</status></result>') !== false;
    }

	/**
	 * Tworzy i zwraca uchwyt do nowego żądania cUrl.
	 *
	 * @return resource $resource cURL handle
	 */
    private function getSingleCurlHandle()
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FAILONERROR => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HEADER => true,
            CURLOPT_VERBOSE => false,
        ]);

        return $ch;
    }

    /**
     * Nie ma opisów graficznych. Zostaje dla kompatybilności.
     */
    public function getUserbars()
    {
        trigger_error('Nie ma opisów graficznych', ((defined('E_USER_DEPRECATED')) ? E_USER_DEPRECATED : E_USER_NOTICE));

        return [];
    }

	/**
	 * Tworzy i zwraca uchwyt do nowego żądania cUrl.
	 *
	 * @param $type
	 * @param $post
	 * @return mixed
	 */
    private function imageCurl($type, $post)
    {
        $data = $this->authorization->getServerAndToken();

        $ch = $this->getSingleCurlHandle();
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['BotApi-Version: '.self::BOTAPI_VERSION, 'Token: '.$data['token'], 'Expect: ']);
        curl_setopt($ch, CURLOPT_URL, 'https://botapi.gadu-gadu.pl/botmaster/'.$type.'Image/'.$this->gg);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        $r = curl_exec($ch);
        curl_close($ch);

        return $r;
    }

    /**
     * Wysyła obrazek do Botmastera.
     *
     * @param $data
     *
     * @return bool
     */
    public function putImage($data)
    {
        if (!$this->authorization->isAuthorized()) {
            return false;
        }

        $r = $this->imageCurl('put', $data);

        return strpos($r, '<result><status>0</status><hash>') !== false;
    }

    /**
     * Pobiera obrazek z Botmastera.
     *
     * @param $hash
     *
     * @return bool
     */
    public function getImage($hash)
    {
        if (!$this->authorization->isAuthorized()) {
            return false;
        }

        $r = explode("\r\n\r\n", $this->imageCurl('get', 'hash='.$hash), 2);

        return $r[1];
    }

    /**
     * Sprawdza czy Botmaster ma obrazek.
     *
     * @param $hash
     *
     * @return bool
     */
    public function existsImage($hash)
    {
        if (!$this->authorization->isAuthorized()) {
            return false;
        }

        $r = $this->imageCurl('exists', 'hash='.$hash);

        return strpos($r, '<result><status>0</status><hash>') !== false;
    }

    /**
     * Sprawdza, czy numer jest botem.
     *
     * @param $ggid
     *
     * @return bool
     */
    public function isBot($ggid)
    {
        if (!$this->authorization->isAuthorized()) {
            return false;
        }

        $data = $this->authorization->getServerAndToken();

        $ch = $this->getSingleCurlHandle();
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['BotApi-Version: '.self::BOTAPI_VERSION, 'Token: '.$data['token']]);
        curl_setopt($ch, CURLOPT_URL, 'https://botapi.gadu-gadu.pl/botmaster/isBot/'.$this->gg);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'check_ggid='.$ggid);

        $r = curl_exec($ch);
        curl_close($ch);

        return strpos($r, '<result><status>1</status></result>') !== false;
    }

    private function checkAuthorization()
    {
        if (!$this->authorization->isAuthorized()) {
            throw new \Exception('Invalid authorization config');
        }
    }
}
