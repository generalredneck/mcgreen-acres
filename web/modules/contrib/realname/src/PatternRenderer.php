<?php

namespace Drupal\realname;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Template\TwigEnvironment;
use Drupal\Core\Utility\Error;
use Drupal\Core\Utility\Token;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use Twig\Error\Error as TwigError;

/**
 * Renders a real name pattern containing tokens and Twig.
 *
 * Token values are passed to Twig as variables, never written into the
 * template, so user-supplied field values cannot inject Twig code.
 */
class PatternRenderer {

  /**
   * Constructs a PatternRenderer object.
   *
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\Core\Template\TwigEnvironment $twig
   *   The Twig environment.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    protected readonly Token $token,
    protected readonly TwigEnvironment $twig,
    protected readonly LoggerInterface $logger,
  ) {}

  /**
   * Renders a real name pattern for a user.
   *
   * @param string $pattern
   *   The real name pattern.
   * @param \Drupal\user\UserInterface $account
   *   The user account.
   *
   * @return string
   *   The rendered pattern, with HTML entities still encoded, or an empty
   *   string if Twig fails.
   */
  public function render(string $pattern, UserInterface $account): string {
    [$template, $variables] = $this->compile($pattern);
    $context = [];
    foreach ($variables as $token => $variable) {
      $context[$variable] = $this->token->replace($token,
        ['user' => $account],
        ['clear' => TRUE, 'sanitize' => FALSE],
        new BubbleableMetadata()
      );
    }
    // Token values arrive already encoded, and callers decode the result to
    // plain text, so escaping them again would double-encode.
    try {
      return (string) $this->twig->renderInline('{% autoescape false %}' . $template . '{% endautoescape %}', $context);
    }
    catch (TwigError $e) {
      // A token value can break a pattern, e.g. one doing arithmetic.
      $this->logger->error('%type: @message in %function (line %line of %file).', Error::decodeException($e));
      return '';
    }
  }

  /**
   * Converts a real name pattern into a Twig template.
   *
   * Each token becomes a reference to a Twig variable, written to suit where
   * the token appears: in text, inside a Twig expression, or inside a string
   * literal within an expression.
   *
   * @param string $pattern
   *   The real name pattern.
   *
   * @return array
   *   The Twig template, and the Twig variable names keyed by token.
   */
  protected function compile(string $pattern): array {
    $variables = [];
    foreach ($this->token->scan($pattern) as $tokens) {
      foreach ($tokens as $token) {
        $variables[$token] = 'realname_token_' . count($variables);
      }
    }

    $template = '';
    // Open Twig constructs, innermost last.
    $stack = [];
    $length = strlen($pattern);
    $i = 0;
    while ($i < $length) {
      $top = array_key_last($stack);
      $type = $top === NULL ? 'text' : $stack[$top]['type'];

      if ($type !== 'comment' && $pattern[$i] === '[') {
        foreach ($variables as $token => $variable) {
          if (substr_compare($pattern, $token, $i, strlen($token)) === 0) {
            $template .= match ($type) {
              'text' => '{{ ' . $variable . ' }}',
              'single' => "' ~ " . $variable . " ~ '",
              'double' => '#{' . $variable . '}',
              default => $variable,
            };
            if ($type === 'single') {
              $stack[$top]['concatenated'] = TRUE;
            }
            $i += strlen($token);
            continue 2;
          }
        }
      }

      $char = $pattern[$i];
      $pair = substr($pattern, $i, 2);
      $consumed = $char;
      switch ($type) {
        case 'text':
          if ($pair === '{{' || $pair === '{%') {
            $stack[] = ['type' => 'tag', 'end' => $pair === '{{' ? '}}' : '%}', 'depth' => 0];
            $consumed = $pair;
          }
          elseif ($pair === '{#') {
            $stack[] = ['type' => 'comment'];
            $consumed = $pair;
          }
          break;

        case 'comment':
          if ($pair === '#}') {
            array_pop($stack);
            $consumed = $pair;
          }
          break;

        case 'tag':
        case 'interpolation':
          $depth = $stack[$top]['depth'];
          if ($depth === 0 && $type === 'tag' && $pair === $stack[$top]['end']) {
            array_pop($stack);
            $consumed = $pair;
          }
          elseif ($depth === 0 && $type === 'interpolation' && $char === '}') {
            array_pop($stack);
          }
          elseif (str_contains('([{', $char)) {
            $stack[$top]['depth']++;
          }
          elseif (str_contains(')]}', $char)) {
            $stack[$top]['depth']--;
          }
          elseif ($char === "'" || $char === '"') {
            $stack[] = [
              'type' => $char === "'" ? 'single' : 'double',
              'start' => strlen($template),
              'concatenated' => FALSE,
            ];
          }
          break;

        case 'single':
        case 'double':
          if ($char === '\\') {
            $consumed = $pair;
          }
          elseif ($type === 'double' && $pair === '#{') {
            $stack[] = ['type' => 'interpolation', 'depth' => 0];
            $consumed = $pair;
          }
          elseif ($char === ($type === 'single' ? "'" : '"')) {
            $string = array_pop($stack);
            // Parenthesize a concatenated string so filters and operators
            // still apply to the whole string.
            if ($string['concatenated']) {
              $template = substr($template, 0, $string['start']) . '(' . substr($template, $string['start']) . "')";
              $consumed = '';
            }
          }
          break;
      }
      $template .= $consumed;
      $i += strlen($consumed) ?: 1;
    }
    return [$template, $variables];
  }

}
