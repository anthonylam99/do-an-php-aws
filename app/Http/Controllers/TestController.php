<?php

namespace App\Http\Controllers;

use FFI;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function test1(Request $request)
    {
        $s = $request->get('s');
        $keyboard = $request->get('keyboard');

        $sArr = str_split($s);
        $keyboardArr = str_split($keyboard);

        $row = [];
        $i = 0;
        $count = 0;
        foreach ($keyboardArr as $value) {

            $row[$i][] = $value;
            $count++;
            if ($count % 3 === 0) {
                $i++;
            }
        }
        $newRow = [];

        foreach ($row as $key => $value) {
            foreach ($value as $key1 => $value1) {
                $newRow[$value1] = [
                    'row' => $key,
                    'col' => $key1
                ];
            }
        }

        $totalTime = 0;

        for ($i = 0; $i < count($sArr); $i++) {
            if (isset($sArr[$i + 1])) {
                if ($sArr[$i] !== $sArr[$i + 1]) {
                    if ($newRow[$sArr[$i]]['row'] === $newRow[$sArr[$i + 1]]['row']) {
                        $totalTime += 1;
                    } else {
                        $totalTime += 2;
                    }
                }
            }
        }


        dd($totalTime);
    }

    public function test2(Request $request)
    {
        $words = $request->get('words');

        $arr = [];
        $arr1 = [];

        foreach ($words as $value) {
            $splitStr = str_split($value);

            for ($i = 0; $i < count($splitStr); $i++) {
                if (isset($splitStr[$i + 1])) {
                    if ($splitStr[$i] === $splitStr[$i + 1]) {
                        $check = 1;
                    } else {
                        $check = 0;
                    }
                    $arr1[$value][$splitStr[$i] . '->' . $splitStr[$i + 1]] = $check;
                }
            }
        }


        foreach ($arr1 as $value) {
            $total = 0;
            foreach ($value as $value1) {
                $total += $value1;
            }
            echo $total . "<br>";
        }
    }

    public function test(Request $request)
    {
        $n = $request->get('n');
        $p = $request->get('p');
        $q = $request->get('q');
        $r = $request->get('r');

        $arr = [$p, $q, $r];
        $arrBS = [];

        $count = 0;
        for ($i = 0; $i < count($arr); $i++) {
            for ($j = $i + 1; $j < count($arr); $j++) {
                if ($arr[$i] !== $arr[$j]) {
                    $arrBS[$arr[$i] . $arr[$j]] = $this->danhSachBoiSo($arr[$i], $arr[$j], $n);
                }
            }
        }

        $arrTest = [];

        foreach ($arrBS as $BS) {
            foreach ($BS as $value) {
                array_push($arrTest, $value);
            }
        }

        $count = 0;
        for ($i = 0; $i < $n; $i++) {
            $search = in_array($i, array_unique($arrTest));

            if ($search) {
                $count++;
            }
        }
        return $count;
    }

    public function danhSachBoiSo($a, $b, $n)
    {
        $boisoA = $boisoB = [];
        for ($i = 1; $i < 10; $i++) {

            array_push($boisoA, $a * $i);
            array_push($boisoB, $b * $i);
        }

        return array_intersect($boisoA, $boisoB);
    }
}
