<?php
ini_set('max_execution_time', 0);
ini_set('memory_limit', '-1');


$depth = !empty($_POST['depth']) ? $_POST['depth'] : 2;
$fen = !empty($_POST['fen']) ? $_POST['fen'] : '8/4k3/8/4p3/4P3/4K3/8/8 w - - 0 1';


require 'vendor/autoload.php';
use Ryanhs\Chess\Chess;

class CostFunction extends Chess {
	
	public function run($depth)
	{
		$result = [];
		$nodes = 0;
		
		$moves = $this->moves([ 'legal' => true ]);
		$color = $this->turn();
		for ($i = 0, $len = count($moves); $i < $len; $i++) {
			$this->move($moves[$i]);
			
			if (!$this->kingAttacked($color)) {
				
				$calculatedMove = $moves[$i].','.$this->fen().','.$this->calculateSimplified();
				
				if ($depth - 1 > 0) {
					$result[$calculatedMove] = $this->run($depth - 1);
				} else {
					$result[$calculatedMove] = $calculatedMove;
				}
			}
			$this->undoMove();
		}
		
		return $result;
	}
	
	public function calculateSimplified()
	{
		$them = $this->turn();
		$us = self::swap_color($them);
		
		// counter
		$counter = [
			'kw' => 0, 	'kb' => 0,
			'qw' => 0, 	'qb' => 0,
			'rw' => 0, 	'rb' => 0,
			'bw' => 0, 	'bb' => 0,
			'nw' => 0, 	'nb' => 0,
			'pw' => 0, 	'pb' => 0,
		];
		foreach ($this->board as $coor => $square) {
			if ($square == null)
				continue;
			
			if (is_array($square)) {				
				$counter[$square['type'].$square['color']]++;
			}
		}
		
		$isolated = ['w' => 0, 'b' => 0];
		$stacked = ['w' => 0, 'b' => 0];
		$doubled = ['w' => 0, 'b' => 0];
		$x88 = self::SQUARES;
		$x88_flip = array_flip(self::SQUARES);
		
		foreach ($this->board as $coor => $square) {
			if ($square == null)
				continue;
			
			if (is_array($square)) {
				// calculate isolated
				$this->turn = $square['color'];
				$tmp = count($this->generateMoves(['legal' => false, 'square' => $coor]));
				if ($tmp == 0) $isolated[$square['color']]++;
				$this->turn = $them;
				
				// calculate doubled/stack
				if ($square['type'] == 'p') {
					if ($coor - 16 > 0) {
						$tmp = $this->board[$coor - 16];
						if ($tmp['type'] == 'p') {
							if ($tmp['color'] == $square['color']) {
								$doubled[$tmp['color']]++;
							} else {
								$stacked[$tmp['color']]++;
							}
						}
					}
					if ($coor + 16 > 0) {
						$tmp = $this->board[$coor + 16];
						if ($tmp['type'] == 'p') {
							if ($tmp['color'] == $square['color']) {
								$doubled[$tmp['color']]++;
							} else {
								$stacked[$tmp['color']]++;
							}
						}
					}
				}
			}
		}
		
		
		// mobility
		$mobility = ['w' => 0, 'b' => 0];
		$this->turn = $us;
		$mobility[$us] = count($this->generateMoves([ 'legal' => true ]));
		$this->turn = $them;
		$mobility[$them] = count($this->generateMoves([ 'legal' => true ]));
		
		
		
		$result = 20000	* ($counter['k'.$us] - $counter['k'.$them])
				+ 900	* ($counter['q'.$us] - $counter['q'.$them])
				+ 500	* ($counter['r'.$us] - $counter['r'.$them])
				+ 330	* ($counter['b'.$us] - $counter['b'.$them])
				+ 320	* ($counter['n'.$us] - $counter['n'.$them])
				+ 100	* ($counter['p'.$us] - $counter['p'.$them])
				- 50	* ($doubled[$us] - $doubled[$them] + $stacked[$us] - $stacked[$them] + $isolated[$us] - $isolated[$them])
				+ 10	* ($mobility[$us] - $mobility[$them])
		;
		
		return $result;
	}
}




/*
 *  run process
 */
$costFunction = new CostFunction($fen);
$turn = $costFunction->turn();
$time_start = microtime(true);

	$calculations = $costFunction->run($depth);

$time_end = microtime(true);
$execution_time = ($time_end - $time_start);







/*
 * alpha-beta pruning
 * */
function alphaBeta($node, $alpha, $beta, $maximisingPlayer) {
    $bestMove = null;
    if (!is_array($node)) {
        $bestMove = $node;
    }
    else if ($maximisingPlayer) {
        $bestMove = $alpha;
        
        // Recurse for all children of node.
        foreach ($node as $k => $v) {
			$childValue = alphaBeta($v, $bestMove, $beta, false);
			$tmpBestMove = max(explode(',',$bestMove)[2], explode(',',$k)[2]);
			$bestMove = explode(',',$bestMove)[2] == $tmpBestMove ? $bestMove : $k;
			
			if (explode(',',$beta)[2] <= explode(',',$bestMove)[2]) {
				break;
			}
		}
    }
    else {
        $bestMove = $beta;
        
        // Recurse for all children of node.
        foreach ($node as $k => $v) {
			$childValue = alphaBeta($v, $alpha, $bestMove, true);
			$tmpBestMove = min(explode(',',$bestMove)[2], explode(',',$k)[2]);
			$bestMove = explode(',',$bestMove)[2] == $tmpBestMove ? $bestMove : $k;
			
			if (explode(',',$bestMove)[2] <= explode(',',$alpha)[2]) {
				break;
			}
		}
    }
    return $bestMove;
}
$bestMove = alphaBeta($calculations, '-,-,-1000000', '-,-,1000000', true);

//~ echo 'bestmove: '.$bestMove.PHP_EOL.PHP_EOL;
//~ print_r($calculations);
//~ exit;



/*
 *  display output
 */
function toggleTurn() {
	global $turn;
	return $turn = $turn == 'w' ? 'b' : 'w';
}
toggleTurn();
$result = [];
$header = ['FEN', 'Simplified'];
for ($i = 1; $i < $depth + 1; $i++) $header[] = '#'.$i.' '.toggleTurn();
function add2Result ($item, $currentDepth, $maxDepth) {
	global $result;
	$tmp = array_fill(0, $maxDepth + 2, '');
	$tmp[0] = explode(',', $item)[1];
	$tmp[1] = explode(',', $item)[2];
	$tmp[$maxDepth - $currentDepth + 1] = explode(',', $item)[0];
	$result[] = $tmp;
}
function convertArray($arr, $currentDepth, $maxDepth) {
	global $result;
	if (is_array($arr)) {
		foreach ($arr as $k => $v) {
			if (is_array($v)) {
				add2Result($k, $currentDepth - 1, $maxDepth);
			}
			
			convertArray($v, $currentDepth - 1, $maxDepth);
		}
	} else {
		add2Result($arr, $currentDepth, $maxDepth);
	}
}
convertArray($calculations, $depth, $depth);
//~ print_r($result);

?>

<table class="tform">
	<tr>
		<td>
			<img src="http://www.fen-to-image.com/image/<?php echo explode(' ', $fen)[0]; ?>" />
		</td>
		<td>
			<form action="" method="post">
				<table class="tform">
					<tr>
						<td>FEN</td>
						<td>&nbsp;:&nbsp;</td>
						<td><input type="text" name="fen" value="<?php echo $fen; ?>" /></td>
					</tr>
					<tr>
						<td>Depth</td>
						<td>&nbsp;:&nbsp;</td>
						<td><input type="text" name="depth" value="<?php echo $depth; ?>" /></td>
					</tr>
					<tr>
						<td colspan="3"><input type="submit" /></td>
					</tr>
				</table>
			</form>
			<hr />
			Best Move: <?php echo explode(',',$bestMove)[0].' ('.explode(',',$bestMove)[2].')'; ?><br/>
			Execution Time: <?php echo number_format($execution_time, 3).'s'; ?><br/><br/>
		</td>
	</tr>
</table>
<br/>
<table class="tresult">
	<thead>
		<tr>
		<?php foreach ($header as $th) : ?>
			<td><?php echo $th; ?></td>
		<?php endforeach; ?>
		</tr>
	</thead>
	<tbody>
	<?php foreach ($result as $row) : ?>
		<tr>
		<?php foreach ($row as $cell) : ?>
			<td><?php echo $cell; ?></td>
		<?php endforeach; ?>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
<style>
table.tresult, table.tresult th, table.tresult td
{
	border-collapse:collapse;
	border: 1px solid black;
	padding: 3px 5px;
	text-align:center;
}
table.tform td:nth-child(1)
{
	vertical-align:top;
	padding: 3px 5px;
}
table.tform td:nth-child(1) img
{
	border:1px solid #aaa;
}
table.tform td:nth-child(2)
{
	vertical-align:top;
	padding: 3px 10px;
}
</style>
