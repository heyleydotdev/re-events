# Page builders

ReEvents ships native widgets for Elementor. Every other builder uses the shortcodes. Both paths call the same renderer with the same arguments, so the output is identical.

## Elementor

- Widgets appear under the **ReEvents** category in the widget panel.
- Every registry argument becomes a native control with the same label, description and default as the shortcode attribute.
- Toggles are switches. Switching one off sends an empty value, which the renderer treats as false.

Elementor is the only builder with an adapter because it is free and can be tested.

## Other builders

Other page builders and block-based themes can use the shortcodes in a text, HTML or code module. The full list and arguments are in the [shortcode reference](../shortcodes/README.md).

```text
[reevents_list limit="3" show_venue="0"]
```

The same widget in Elementor with `limit = 3` and `Show venue` off produces the same HTML.

## No styles

ReEvents ships no frontend CSS. The builder and the theme own spacing, colors and typography. Every widget accepts `class` and `wrapper_class`.
