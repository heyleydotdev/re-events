import { marked } from "marked";
import { readFile, writeFile } from "node:fs/promises";

const docs = ["auth"];

for (const name of docs) {
	const source = `docs/api/${name}.md`;
	const target = `docs/api/${name}.html`;
	const markdown = await readFile(source, "utf8");
	const html = await marked.parse(markdown);

	await writeFile(target, html);
	console.log(`built ${target}`);
}
