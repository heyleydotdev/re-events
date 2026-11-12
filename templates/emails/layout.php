<?php
/**
 * Booking email layout.
 *
 * Override in the theme at re-events/emails/layout.php.
 *
 * @package ReEvents
 *
 * @var array{subject:string,message:string,details:array<int, array{label:string,value:string}>,event_url:string,site_name:string,site_url:string,logo_url:string} $args
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are scoped to the template include.

$site_name = (string) $args['site_name'];
$site_url  = (string) $args['site_url'];
$logo_url  = (string) $args['logo_url'];
$button    = (string) $args['event_url'];
?>
<!doctype html>
<html lang="<?php echo esc_attr( (string) get_bloginfo( 'language' ) ); ?>">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?php echo esc_html( (string) $args['subject'] ); ?></title>
</head>
<body style="margin:0;padding:0;background:#f6f7f7;color:#1d2327;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f6f7f7;">
<tr>
<td align="center" style="padding:24px 12px;">
<table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border:1px solid #dcdcde;border-radius:6px;">
<tr>
<td style="padding:24px 28px;border-bottom:1px solid #dcdcde;">
<?php if ( '' !== $logo_url ) : ?>
<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" width="48" height="48" style="display:block;border:0;margin:0 0 12px;" />
<?php endif; ?>
<a href="<?php echo esc_url( $site_url ); ?>" style="color:#1d2327;font-size:20px;font-weight:600;text-decoration:none;"><?php echo esc_html( $site_name ); ?></a>
</td>
</tr>
<tr>
<td style="padding:28px;font-size:16px;line-height:1.5;">
<?php echo $args['message']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped and formatted before rendering. ?>
<?php if ( $args['details'] ) : ?>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:20px;border-top:1px solid #dcdcde;">
	<?php foreach ( $args['details'] as $row ) : ?>
<tr>
<td style="padding:8px 12px 8px 0;color:#646970;vertical-align:top;width:34%;"><?php echo esc_html( $row['label'] ); ?></td>
<td style="padding:8px 0;vertical-align:top;"><?php echo nl2br( esc_html( $row['value'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<?php if ( '' !== $button ) : ?>
<p style="margin:28px 0 0;">
<a href="<?php echo esc_url( $button ); ?>" style="display:inline-block;padding:12px 20px;background:#2271b1;color:#ffffff;text-decoration:none;border-radius:4px;font-weight:600;"><?php esc_html_e( 'View event', 're-events' ); ?></a>
</p>
<?php endif; ?>
</td>
</tr>
<tr>
<td style="padding:18px 28px;border-top:1px solid #dcdcde;color:#646970;font-size:13px;line-height:1.5;">
<?php
printf(
	/* translators: %s: site name. */
	esc_html__( 'This message was sent by %s.', 're-events' ),
	esc_html( $site_name )
);
?>
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>
