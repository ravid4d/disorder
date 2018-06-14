<?php

namespace AmcLab\Disorder;

class Disorder {

    // questa classe gestisce i dizionari prima di darli in pasto al Maskable per cifrare/decifrare

    protected const MIN_SIZE = 9;

    protected const DEFAULT_DICTIONARY = [
        [ 'ąáaăāãâäàå', 'b', 'çĉčćcċ', 'dď', 'éêëèęeēėĕě', 'f', 'ğģĝġg', 'ĥh', 'ĩĭiīįíîïì', 'jĵ', 'kķ', 'ľļlĺ', 'm', 'ńñnņň', 'ōŏoõőòöôó', 'p', 'q', 'ŗrřŕ', 'sŝşšś', 'tťţ', 'ůùűúûüuŭūųũ', 'v', 'wŵ', 'x', 'ýyÿŷ', 'zżžź', 'æðøþđħĳŀłŋœŧ' ],
        '£€«»¡¿“”‘’‹›÷¶' . '!"#$%&\'()*+,-./:;<=>?@[\]^_`{|}~' . '0123456789' . ' ',
    ];

    protected $dictionary;

    protected $maxIncrements = 999999;

    protected $localKey;
    protected $indexKey;

    public function __construct(array $dictionary = null, string $key = null) {
        if ($dictionary) {
            $this->init($dictionary, $key);
        }
    }

    public function init(array $dictionary = null, string $key = null) : self {

        $original = $dictionary['original'] ?? self::DEFAULT_DICTIONARY;
        $shuffled = $dictionary['shuffled'] ?? self::DEFAULT_DICTIONARY;

        $shuffle = $shuffled === self::DEFAULT_DICTIONARY;

        $totals = [];

        foreach ($shuffled as $idx => &$chars) {

            $max = 0;

            $size = is_array($chars) ? count($chars) : mb_strlen($chars);
            $chars = is_array($chars) ? implode('', $chars) : $chars;
            $max = $size > $max ? $size : $max;

            if ($len = mb_strlen($chars)) {

                $diffSize = 0;

                $chars = $shuffle ? $this->shuffleDictionary($chars) : $chars;

                if ($len<$this->maxIncrements) {
                    $this->maxIncrements = $len - 1;
                    if ($this->maxIncrements < self::MIN_SIZE) {
                        throw new \Exception('CHAR_LIST_TOO_SMALL: minimum of ' . self::MIN_SIZE . ' chars required ');
                    }
                }

                for ($i = 0; $i < mb_strlen ($chars); $i++) {
                    $one = mb_substr($chars, $i, 1);
                    $index = mb_strtolower($one);
                    $upperIndex = mb_strtoupper($one);

                    if ($upperIndex!==$index) {
                        $diffSize++;
                    }

                    $totals[$index] = ($totals[$index] ?? 0) + 1;

                    if ($totals[$index] > 1) {
                        throw new \Exception('DUPLICATE_DICTIONARY_ENTRY: value «'.$one.'» in index «'.$index.'»');
                    }
                }

                if( ! ( ($diffSize === mb_strlen($chars)) || ($diffSize===0) ) ){
                    throw new \Exception('MIXED_CASED_CHARS: on array index «'.$idx.'»');
                }

            }
            else {
                throw new \Exception('EMPTY_CHAR_LIST');
            }

        }

        $this->dictionary = [
            'original' => $original,
            'shuffled' => $shuffled,
        ];

        $this->localKey = hash('md5', ($key === null ? base64_decode(substr(env('APP_KEY'),7)) : $key));
        $this->indexKey = hash('md5', $this->localKey);

        return $this;
    }

    protected function shuffleDictionary(string $chars) : string {
        $len = mb_strlen($chars);
        $exploded = [];
        while ($len-->0) {
            $exploded[] = mb_substr($chars, $len, 1);
        }
        shuffle($exploded);
        return implode('', $exploded);
    }

    public function using(string $string = '', array $config = [1,1]) : object {

        if (!$this->dictionary) {
            throw new \Exception('Disorder must be initialized');
        }

        return new Maskable($this, $string, $config);

    }

    public function getDictionary($index = null) {
        return $index ? $this->dictionary[$index] : $this->dictionary;
    }

    public function getLocalKey() {
        return $this->localKey;
    }

    public function getIndexKey() {
        return $this->indexKey;
    }

    public function getMaxIncrements() {
        return $this->maxIncrements;
    }

}
