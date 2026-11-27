import path from 'node:path';
import { pathToFileURL } from 'node:url';

const [moduleFile, corpusJson] = process.argv.slice(2);

const { countPromptWords } = (await import(pathToFileURL(path.resolve(moduleFile)).href)) as {
    countPromptWords: (text: string) => number;
};

const corpus = JSON.parse(corpusJson) as string[];

console.log(JSON.stringify(corpus.map((text) => countPromptWords(text))));
