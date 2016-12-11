<?php
/*
 * This is a Reverse Polish Notation Calculator, feel free to edit to your liking.
 *
 * Copyright (c) 2016 ultimater at gmail dot com
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

// This class implements the Shunting-yard algorithm: https://en.wikipedia.org/wiki/Shunting-yard_algorithm
class MathCalculator
{
    protected $error, $result, $parsed;
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
                if( $x !== FALSE && $this->isNumeric("{$x}0" ) )
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
            if( !$this->isNumeric($token) ) throw new Exception("Expected a numeric token: ". $token); //e.g. 1.2.3.4
            $outArray[] = $token;
        }
        return $outArray;
    } // tokenize

    protected function isOperator($token)
    {
        return preg_match("#^[/+*-]$#", $token);
    } // isOperator

    // Unlike the built-in is_numeric, this function doesn't allow leading space, or a leading sign.
    protected function isNumeric($token)
    {
        return preg_match("#^[0-9]+(?:\\.[0-9]+)?$#", $token);
    } // isNumeric

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
            if( $this->isNumeric($token) ){ $outputQueue[] = $token; continue; }
            if( $this->isOperator($token) )
            {
                while( ( $op = end($operatorStack) ) && $op !== NULL && $this->isOperator($op) && $this->getPrecedence($token) <= $this->getPrecedence($op))
                {
                    $outputQueue[] = array_pop($operatorStack);
                }
                $operatorStack[] = $token; continue;
            }
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
            if( $this->isNumeric($token) ) {$stack[] = $token;continue;}
            if( !$this->isOperator($token) ) throw new Exception("Expected an operator: $token");
            $op = $token;
            $b = array_pop($stack);
            $a = array_pop($stack);
            if($op == '+') $stack[] = $a + $b;
            if($op == '-') $stack[] = $a - $b;
            if($op == '*') $stack[] = $a * $b;
            if($op == '/'){ if($b == 0) throw new Exception("Error: Cannot divide by 0"); $stack[] = $a / $b; }
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
    } // input

} // MathCalculator

  // Post-Redirect-Get (friendly for browser back buttons)
  session_start();
  if(isset($_POST['input']))
  {
    $_SESSION['post'] = $_POST;
    header('Location: '.$_SERVER['REQUEST_URI']); // RFC 2616 requires an absolute URI, but as of RFC 7231 relative URIs are permitted.
    exit;
  }
  // once the redirect is complete, we continue using $_POST like there was no redirect at all
  if(isset($_SESSION['post']))
  {
    $_POST = $_SESSION['post'];
    unset($_SESSION['post']);
  }
  session_write_close(); // The sooner we do this, the sooner we free up other waiting requests

  $infix = '';
  $postfix = '';
  $result = null;
  if(isset($_POST['input']) && $_POST['input'] == 'infix' && isset($_POST['infixExpr']) && $_POST['infixExpr'] != '')
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
