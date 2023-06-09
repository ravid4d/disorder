<?php

namespace AmcLab\Disorder;

class Maskable {

    // implementazione per la cifratura/decifratura

    // TODO: ottimizzare pass() e varie altre ottimizzazioni da fare...

    protected $injected;
    protected $string;
    protected $config;

    protected const FORTH = 1;
    protected const BACK = -1;

    public function __construct($injected, $string, $config){
        $this->injected = $injected;
        $this->string = $string;
        $this->config = $config;
    }

    public function getMasked() : string {
        return $this->xor($this->pass($this->string, $this->config, self::FORTH));
    }

    public function getUnmasked() : string {
        return $this->pass($this->xor($this->string), $this->config, self::BACK);
    }

    public function getIndexed() : string {
        return $this->index($this->string, $this->config);
    }

    protected function xor(string $input) : string {
        $final = '';
        $offset = mb_strlen ($input);
        for ($i = 0; $i < $offset; $i++) {
            $source = mb_ord(mb_substr($input, $i, 1));
            $mask = ord(substr($this->injected->getLocalKey(), ($i+$offset) % strlen($this->injected->getLocalKey()), 1));
            $val = $source ^ $mask;
            $final .= $val ? mb_chr($val) : chr(0);
        }
        return $final;
    }

    // TODO: bozza (incompleta e da testare) per sostituire il vecchio index() ed eliminare translate()

    // protected function index(string $input, array $config = [1,1]) : string {

    //     [$add, $skip] = $config;
    //     $indexKey = $this->injected->getIndexKey();
    //     $dictionary = $this->injected->getDictionary('original');

    //     $result = '';

    //     // appiattisco il dizionario
    //     foreach(new \RecursiveIteratorIterator(new \RecursiveArrayIterator($dictionary)) as $one) {
    //         $flattenedDictionary[] = $one;
    //     }

    //     // scorro ciascun carattere della stringa $input
    //     for ($i = 0; $i < mb_strlen($input); $i++) {

    //         $group = 0;
    //         $at = 0;
    //         $currentChar = mb_strtolower(mb_substr($input, $i, 1));

    //         // cerco il carattere corrente
    //         foreach($flattenedDictionary as $idx => $block) {

    //             $position = mb_strpos($block, $currentChar);

    //             // se lo trovo, memorizzo la posizione e fermo il ciclo
    //             if ($position !== false) {

    //                 // aggiungo il primo parametro di config all'indice
    //                 $group = $idx + $add;

    //                 // riduco la posizione ad un valore massimo di 4 bit
    //                 $at = $position % 16;

    //                 break;
    //             }
    //         }

    //         // creo il valore di base (gruppo spostato a sx di 4 bit + posizione "ridotta" a 4 bit)
    //         $base = ($group << 4) + $at;

    //         // prendo il valore della chiave indice alla posizione corrente
    //         $keyValue = hexdec(substr($indexKey, $i % strlen($indexKey), 1));

    //         // costruisco il valore finale sommando base, keyValue e posizione corrente
    //         // moltiplicata per il secondo parametro di config
    //         $final = $base + $keyValue + ($i*$skip);

    //         // e lo concateno
    //         $result .= $final ? mb_chr($final) : chr(0);

    //         // dump(str_pad(decbin($final), 16, '0', STR_PAD_LEFT));

    //     }

    //     return $result;
    // }

    protected function translate(string $list, string $search, int $index = null) {
        $position = mb_strpos($list, $search);
        return $position !== false ? ($index ?? $position) : false;
    }

    protected function index(string $input, array $config = [1,1]) : string {

        [$offset, $mult] = $config;

        $offset += 0x14;
        $mult += 0x33 / 3;

        $output = '';


        for ($i = 0; $i < mb_strlen ($input); $i++) {
            $found = false;

            foreach ($this->injected->getDictionary('original') as $index => $group) {
                if ($found === false){
                    $char = mb_strtolower(mb_substr($input, $i, 1));

                    if (is_array($group)) {
                        foreach ($group as $sub => $inner) {
                            $found = $found !== false ? $found : $this->translate($inner, $char, $sub);
                        }
                    }

                    else {
                        $found = $this->translate($group, $char);
                    }

                    $offset = $i + floor($offset/2) + ord(substr($this->injected->getIndexKey(), $i % strlen($this->injected->getIndexKey()), 1)) * $mult;

                    if ($found !== false) {
                        $prevSize = mb_strlen(implode('', array_slice($this->injected->getDictionary('shuffled'), 0, $index)));
                        $res = $found + $prevSize + $offset;
                    }

                    else {
                        $res = $offset;
                    }

                    $output .= $res ? mb_chr($res) : chr(0);
                }
            }
        }
        return $output;
    }

    protected function groupEntry(string $group, int $position) : string {
        return mb_substr($group, $position % (mb_strlen($group)) , 1);
    }

    // TODO: da rivedere. è poco ottimizzata.
    // è ricorsiva e può restituire array (prima chiamata) o string (chiamate annidate)
    protected function pass(string $input, array $config = [1,1], int $direction, array $processables = null) {

        [$add, $skip] = $config;

        if ($this->injected->getMaxIncrements()) {
            if ($add % $this->injected->getMaxIncrements() === 0) {
                $add = $add+1;
            }
        }

        if ($processables === null) {
            $processables = $this->injected->getDictionary('shuffled');
        }

        if ($processables) {
            $current = mb_strtolower(array_shift($processables));

            $output = '';

            for ($i = 0; $i < mb_strlen ($input); $i++) {

                $uppercase = false;
                $original = mb_substr($input, $i, 1);
                $found = mb_strpos($current, $original);

                if ($found===false) {
                    $found = mb_strpos(mb_strtoupper($current), $original);
                    if ($found!==false) {
                        $uppercase = true;
                    }
                }

                $offset = (($i*$skip)+$add)*$direction;
                $becomes = $this->groupEntry($current, $found + $offset);
                if ($uppercase) {
                    $becomes = mb_strtoupper($becomes);
                }

                $output .= $found === false ? $original : $becomes;

            }

            return $this->pass($output, $config, $direction, $processables);
        }

        else {
            return $input;
        }
    }
}
