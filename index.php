<?php
class MathCalculator
{
    protected $error, $result, $parsed, $hasError;
    protected function tokenize($str)
    {
        $level = 0;
        $tokens = array();
        foreach( str_split($str) as $c )
        {
            if( $level < 0 ) throw new Exception("Mismatched parentheses.");
            if( $c == '(' ){ $level++;$tokens[]=$c;continue; }
            if( $c == ')' ){ $level--;$tokens[]=$c;continue; }
            if( $c == ' ' || $this->isOperator($c) ){ $tokens[] = $c;continue; }
            if( preg_match("#^[0-9.]$#", $c) ) // we're constructing a numeric token
            {
                $x = end($tokens);  // get the last array element or FALSE for an empty array
                if( $x !== FALSE && $x != ' ' && is_numeric("{$x}0" ) ) // we check for a space here as is_numeric is too forgiving
                {
                    $tokens[key($tokens)] .= $c;  // append the character to the string we've been constructing
                    continue;
                }
                $tokens[] = $c;continue; // otherwise we got a leading decimal point or our array is empty
            }
            throw new Exception("Unsupported Character: ".$c);
        }
        if( $level != 0 ) throw new Exception( sprintf("Missing %s parentheses.", $level > 0 ? 'right' : 'left') );
        $outArray = array();
        foreach( $tokens as $token )
        {
            if( $token == ' ' )continue;
            if( preg_match("#^[/+*()-]$#", $token) ){ $outArray[] = $token;continue; }
            if( preg_match("#^\\d+\\.+$#", $token) ) throw new Exception("Numbers cannot end with a decimal point: ". $token);
            if( !is_numeric($token) ) throw new Exception("Expected a numeric token: ". $token); //e.g. 1.2.3.4
            $outArray[] = $token;
        }
        return $outArray;
    } // tokenize

    protected function isOperator($token)
    {
        return preg_match("#^[/+*-]$#", $token);
    } //isOperator

    protected function getPrecedence($op)
    {
        if($op == '+' || $op == '-') return 1;
        if($op == '*' || $op == '/') return 2;
        throw new Exception("Unknown operator: ". $op);
    } // getPrecedence

    protected function parse($str)
    {
        $tokens = $this->tokenize("$str");
        $outputQueue = array();
        $operatorStack = array();
        foreach( $tokens as $token )
        {
            if( is_numeric($token) ){ $outputQueue[] = $token; continue; }
            ////////////////////////////
            if( $this->isOperator($token) )
            {
                while( ( $op = end($operatorStack) ) && $op !== NULL && $this->isOperator($op) && $this->getPrecedence($token) <= $this->getPrecedence($op))
                {
                    $outputQueue[] = array_pop($operatorStack);
                }
                $operatorStack[] = $token; continue;
            }
            /////////////////////////
            if( $token == '(' ){ $operatorStack[] = '('; continue; }
            if( $token == ')' )
            {
                while( ( $op = array_pop($operatorStack) ) != '(' )
                {
                    if( $op === NULL) throw new Exception("Mismatched parentheses.");
                    if( $this->isOperator($op) ) { $outputQueue[] = $op; }
                }
            }
        }
        while( ( $op = array_pop($operatorStack) ) !== NULL )
        {
            if($op == '(' || $op == ')') throw new Exception("Mismatched parentheses.");
            if( $this->isOperator($op) ) { $outputQueue[] = $op; }
        }
        return $outputQueue;
    } // parse

    protected function compute($ar)
    {
        foreach( $ar as $token )
        {
            if( is_numeric($token) ) {$stack[] = $token;continue;}
            if( !$this->isOperator($token) ) throw new Exception("Expected an operator: $token");
            $op = $token;
            $b = array_pop($stack);
            $a = array_pop($stack);
            if($op == '+') $stack[] = $a + $b;
            if($op == '-') $stack[] = $a - $b;
            if($op == '*') $stack[] = $a * $b;
            if($op == '/') $stack[] = $a / $b;
        }
        $result = end($stack);
        return $result;
    } // compute

    public function getLastError()
    {
        return $this->error;
    }  // getError

    public function getResult()
    {
        return $this->result;
    }  // getResult

    public function getRPN()
    {
        return implode(' ', $this->parsed);
    }  // getRPN

    public function input($str)
    {
        try
        {
            $this->parsed = $this->parse($str);
            $this->result = $this->compute($this->parsed);
            return true;
        } catch(Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    } // __construct

} // MathCalculator

  // Post-Redirect-Get (friendly for browser back buttons)
  session_start();
  if(isset($_POST['input']))
  {
    $_SESSION['post']=$_POST;
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
  }
  // once the redirect is complete, we continue using $_POST like there was no redirect at all
  if(isset($_SESSION['post']))
  {
    $_POST = $_SESSION['post'];
    unset($_SESSION['post']);
  }

  $infix = '';
  $postfix = '';
  $result = null;
  if(isset($_POST['input']) && $_POST['input'] == 'infix' && !empty($_POST['infixExpr']))
  {
      $infix = $_POST['infixExpr'];
      $calc = new MathCalculator;
      if($calc->input($_POST['infixExpr']))
      {
        $postfix = $calc->getRPN();
        $result = $calc->getResult();
      } else{
        echo $calc->getLastError();
      }
        
  }

?>

<form method="post">
<table>
<tr><td><label for="infixExpr">Infix<label></td><td><input type="text" id="infixExpr" name="infixExpr" value="<?= htmlentities($infix) ?>"><br>(e.g. 18 / (9 + 9) + 7 - 5)</td></tr>
<tr><td colspan="2" style="text-align:right"><button type="submit" name="input" value="infix">Compute</button></td></tr>
<tr><td><label for="postfixExp">Postfix</label></td><td><input type="text" id="postfixExpr" readonly="readonly" name="postfixExpr" value="<?= htmlentities($postfix) ?>"></td></tr>
</table>
</form>

<?php
if(isset($result))
{
    echo "<p>Result: $result</p>";
}
?>
