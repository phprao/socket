<?php
/**
 * +----------------------------------------------------------
 * date: 2020/5/24 0024 11:58
 * +----------------------------------------------------------
 * author: Raoxiaoya
 * +----------------------------------------------------------
 * describe:
 * +----------------------------------------------------------
 */

//
//function gen() {
//    $ret = (yield 'yield1');
//    var_dump($ret);
//    $ret = (yield 'yield2');
//    var_dump($ret);
//}
//$gen = gen();
//var_dump($gen->current()); // yield1
//var_dump($gen->send('ret1')); // ret1 yield2
//var_dump($gen->send('ret2')); // ret2 NULL



//function gen3(){
//    echo "test\n";
//    echo (yield 1)."I\n";
//    echo (yield 2)."II\n";
//    echo (yield 3 + 1)."III\n";
//}
//$gen = gen3();
//foreach ($gen as $key => $value) {
//    echo "{$key} - {$value}\n";
//}




//$gen = gen3();
//$gen->rewind();
//echo $gen->key().' - '.$gen->current()."\n";
//echo $gen->send("send value - ");


//function gen() {
//    yield 'foo';
//    yield 'bar';
//}
//$gen = gen();
//var_dump($gen->send('something'));


$generator = call_user_func(function() {
    $input = (yield "foo");
    print "inside: " . $input . "\n";
});
print $generator->current() . "\n";
$generator->send("bar");