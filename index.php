<?php
ini_set('max_execution_time', 0);
ini_set('memory_limit', '-1');


$depth = !empty($_POST['depth']) ? $_POST['depth'] : 3;
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
				
				$calculatedMove = $moves[$i].','.$this->fen().','.$this->calculateShannon().','.$this->calculateSimplified();
				
				if ($depth - 1 > 0) {
					$result[$calculatedMove] = $this->run($depth - 1);
				} else {
					$result[] = $calculatedMove;
				}
			}
			$this->undoMove();
		}
		
		return $result;
	}
	
	public function calculateShannon()
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
		
		
		
		$result = 200	* ($counter['k'.$us] - $counter['k'.$them])
				+ 9		* ($counter['q'.$us] - $counter['q'.$them])
				+ 5		* ($counter['r'.$us] - $counter['r'.$them])
				+ 3		* ($counter['b'.$us] - $counter['b'.$them])
				+ 3		* ($counter['n'.$us] - $counter['n'.$them])
				+ 1		* ($counter['p'.$us] - $counter['p'.$them])
				- 0.5	* ($doubled[$us] - $doubled[$them] + $stacked[$us] - $stacked[$them] + $isolated[$us] - $isolated[$them])
				+ 0.1	* ($mobility[$us] - $mobility[$them])
		;
		
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



$costFunction = new CostFunction($fen);
$calculations = $costFunction->run($depth * 2);





$result = [];
$header = ['FEN', 'C. Shannon', 'Simplified'];
for ($i = 1; $i < $depth * 2 + 1; $i++) $header[] = '#'.$i;
function add2Result ($item, $currentDepth, $maxDepth) {
	global $result;
	$tmp = array_fill(0, $maxDepth + 3, '');
	$tmp[0] = explode(',', $item)[1];
	$tmp[1] = explode(',', $item)[2];
	$tmp[2] = explode(',', $item)[3];
	$tmp[$maxDepth - $currentDepth + 2] = explode(',', $item)[0];
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
convertArray($calculations, $depth * 2, $depth * 2);
//~ print_r($result);

?>
<form action="" method="post">
	FEN: <input type="text" name="fen" value="<?php echo $fen; ?>" /><br/>
	Depth: <input type="text" name="depth" value="<?php echo $depth; ?>" /><br/>
	<input type="submit" />
</form>
<hr />
<table>
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
table, th, td
{
  border-collapse:collapse;
  border: 1px solid black;
  padding: 3px 5px;
  text-align:center;
}
</style>
