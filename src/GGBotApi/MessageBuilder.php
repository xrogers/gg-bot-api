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
 * @brief Reprezentacja wiadomości
 */
class MessageBuilder
{
    const IMG_RAW = 0;
    const IMG_FILE = 1;

    const FORMAT_NONE = 0x00;
    const FORMAT_BOLD_TEXT = 0x01;
    const FORMAT_ITALIC_TEXT = 0x02;
    const FORMAT_UNDERLINE_TEXT = 0x04;
    const FORMAT_NEW_LINE = 0x08;

    /**
     * Tablica numerów GG do których ma trafić wiadomość.
     */
    public $recipientNumbers = [];

    /**
     * @var array
     *            Obrazki do wysyłki
     */
    protected $images = [];

    /**
     * Określa czy wiadomość zostanie wysłana do numerów będących offline, domyślnie true.
     */
    public $sendToOffline = true;

    public $html = '';
    public $text = '';
    public $format = '';

    public $R = 0;
    public $G = 0;
    public $B = 0;
    /**
     * @var string
     */
    private $botApiVersion;

    /**
     * Konstruktor MessageBuilder.
     *
     * @param string $botApiVersion
     */
    public function __construct($botApiVersion = PushConnection::BOTAPI_VERSION)
    {
        $this->botApiVersion = $botApiVersion;
    }

    /**
     * Czyści całą wiadomość.
     */
    public function clear()
    {
        $this->recipientNumbers = [];

        $this->sendToOffline = true;

        $this->html = '';
        $this->text = '';
        $this->format = '';

        $this->R = 0;
        $this->G = 0;
        $this->B = 0;
    }

	/**
	 * Dodaje tekst do wiadomości.
	 *
	 * @param string $text tekst do wysłania
	 * @param int $formatBits styl wiadomości (self::FORMAT_BOLD_TEXT, self::FORMAT_ITALIC_TEXT, self::FORMAT_UNDERLINE_TEXT), domyślnie brak
	 *
	 * @param int $R składowe koloru tekstu w formacie RGB
	 * @param int $G
	 * @param int $B
	 *
	 * @return MessageBuilder this
	 */
    public function addText($text, $formatBits = self::FORMAT_NONE, $R = 0, $G = 0, $B = 0)
    {
        if ($formatBits & self::FORMAT_NEW_LINE) {
            $text .= "\r\n";
        }

        $text = str_replace("\r\r", "\r", str_replace("\n", "\r\n", $text));
        $html = str_replace("\r\n", '<br>', htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8'));

        if ($this->format !== null) {
            $this->format .= pack(
                'vC',
                mb_strlen($this->text, 'UTF-8'),
                (($formatBits & self::FORMAT_BOLD_TEXT)) |
                (($formatBits & self::FORMAT_ITALIC_TEXT)) |
                (($formatBits & self::FORMAT_UNDERLINE_TEXT)) |
                ((1 || $R != $this->R || $G != $this->G || $B != $this->B) * 0x08)
            );
            $this->format .= pack('CCC', $R, $G, $B);

            $this->R = $R;
            $this->G = $G;
            $this->B = $B;

            $this->text .= $text;
        }

        if ($R || $G || $B) {
            $html = '<span style="color:#'.str_pad(dechex($R), 2, '0', STR_PAD_LEFT).str_pad(dechex($G), 2, '0', STR_PAD_LEFT).str_pad(dechex($B), 2, '0', STR_PAD_LEFT).';">'.$html.'</span>';
        }
        if ($formatBits & self::FORMAT_BOLD_TEXT) {
            $html = '<b>'.$html.'</b>';
        }
        if ($formatBits & self::FORMAT_ITALIC_TEXT) {
            $html = '<i>'.$html.'</i>';
        }
        if ($formatBits & self::FORMAT_UNDERLINE_TEXT) {
            $html = '<u>'.$html.'</u>';
        }

        $this->html .= $html;

        return $this;
    }

    /**
     * Dodaje tekst do wiadomości.
     *
     * @param string $bbcode tekst do wysłania w formacie BBCode
     *
     * @return MessageBuilder this
     */
    public function addBBcode($bbcode)
    {
        $tagsLength = 0;
        $heap = [];
        $start = 0;
        $bbcode = str_replace('[br]', "\n", $bbcode);

        while (preg_match('/\[(\/)?(b|i|u|color)(=#?[0-9a-fA-F]{6})?\]/', $bbcode, $out, PREG_OFFSET_CAPTURE, $start)) {
            $s = substr($bbcode, $start, $out[0][1] - $start);
            if (strlen($s)) {
                $flags = 0;
                $c = [0, 0, 0];
                foreach ($heap as $h) {
                    switch ($h[0]) {
                        case 'b': {
                            $flags |= 0x01;
                            break;
                        }
                        case 'i': {
                            $flags |= 0x02;
                            break;
                        }
                        case 'u': {
                            $flags |= 0x04;
                            break;
                        }
                        case 'color': {
                            $c = $h[1];
                            break;
                        }
                    }
                }

                $this->addText($s, $flags, $c[0], $c[1], $c[2]);
            }

            $start = $out[0][1] + strlen($out[0][0]);

            if ($out[1][0] == '') {
                switch ($out[2][0]) {
                    case 'b':
                    case 'i':
                    case 'u': {
                        array_push($heap, [$out[2][0]]);
                        break;
                    }

                    case 'color': {
                        $c = hexdec(substr($out[3][0], -6, 6));
                        $c = [
                            ($c >> 16) & 0xFF,
                            ($c >> 8) & 0xFF,
                            ($c >> 0) & 0xFF,
                        ];

                        array_push($heap, ['color', $c]);
                        break;
                    }
                }

                $tagsLength += strlen($out[0][0]);
            } else {
                array_pop($heap);
                $tagsLength += strlen($out[0][0]);
            }
        }

        $s = substr($bbcode, $start);
        if (strlen($s)) {
            $this->addText($s);
        }

        return $this;
    }

    /**
     * Dodaje tekst do wiadomości.
     *
     * @param string $text tekst do wysłania w HTMLu
     *
     * @return MessageBuilder this
     */
    public function addRawHtml($html)
    {
        $this->html .= $html;

        return $this;
    }

    /**
     * Ustawia tekst do wiadomości.
     *
     * @param string $html tekst do wysłania w HTMLu
     *
     * @return MessageBuilder this
     */
    public function setRawHtml($html)
    {
        $this->html = $html;

        return $this;
    }

    /**
     * Ustawia tekst wiadomości alternatywnej.
     *
     * @param string $message tekst do wysłania dla GG7.7 i starszych
     *
     * @return MessageBuilder this
     */
    public function setAlternativeText($message)
    {
        $this->format = null;
        $this->text = $message;

        return $this;
    }

    /**
     * Dodaje obraz do wiadomości.
     *
     * @param string $fileContent nazwa pliku w formacie JPEG
     * @param int    $isFile
     *
     * @return MessageBuilder this
     *
     * @throws \Exception
     */
    public function addImage($fileContent, $isFile = self::IMG_FILE)
    {
        if ($isFile == self::IMG_FILE) {
            $fileContent = file_get_contents($fileContent);
        }

        $crc = crc32($fileContent);
        $hash = sprintf('%08x%08x', $crc, strlen($fileContent));

        $this->images[$hash] = $fileContent;

        $this->format .= pack('vCCCVV', strlen($this->text), 0x80, 0x09, 0x01, strlen($fileContent), $crc);
        $this->addRawHtml('<img name="'.$hash.'">');

        return $this;
    }

    /**
     * Ustawia odbiorców wiadomości.
     *
     * @param int|string|array recipientNumbers numer GG adresata (lub tablica)
     *
     * @return MessageBuilder this
     */
    public function setRecipients($recipientNumbers)
    {
        $this->recipientNumbers = (array) $recipientNumbers;

        return $this;
    }

	/**
	 * Zawsze dostarcza wiadomość.
	 *
	 * @param $sendToOffline
	 * @return MessageBuilder this
	 */
    public function setSendToOffline($sendToOffline)
    {
        $this->sendToOffline = $sendToOffline;

        return $this;
    }

    /**
     * Tworzy sformatowaną wiadomość do wysłania do BotMastera.
     */
    public function getProtocolMessage()
    {
        if (preg_match('/^<span[^>]*>.+<\/span>$/s', $this->html, $o)) {
            if ($o[0] != $this->html) {
                $this->html = '<span style="color:#000000; font-family:\'MS Shell Dlg 2\'; font-size:9pt; ">'.$this->html.'</span>';
            }
        } else {
            $this->html = '<span style="color:#000000; font-family:\'MS Shell Dlg 2\'; font-size:9pt; ">'.$this->html.'</span>';
        }

        $s = pack('VVVV', strlen($this->html) + 1, strlen($this->text) + 1, 0, ((empty($this->format)) ? 0 : strlen($this->format) + 3)).$this->html."\0".$this->text."\0".((empty($this->format)) ? '' : pack('Cv', 0x02, strlen($this->format)).$this->format);

        return $s;
    }

    /**
     * Zwraca na wyjście sformatowaną wiadomość do wysłania do BotMastera.
     */
    public function reply()
    {
        if (sizeof($this->recipientNumbers)) {
            header('To: '.implode(',', $this->recipientNumbers));
        }
        if (!$this->sendToOffline) {
            header('Send-to-offline: 0');
        }

        header('BotApi-Version: '.$this->botApiVersion);

        echo $this->getProtocolMessage();
    }

    public function getImages()
    {
        return $this->images;
    }
}
