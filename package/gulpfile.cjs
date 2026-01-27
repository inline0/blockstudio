const cheerio = require('gulp-cheerio');
const fetch = require('node-fetch');
const fs = require('fs');
const mergeStream = require('merge-stream');
const path = require('path');
const rename = require('gulp-rename');
const { series, src, dest } = require('gulp');
const postcss = require('postcss');
const graceful = require('graceful-fs');
const tailwindcss = require('tailwindcss');
const cssnano = require('cssnano');

const createIcons = () => {
  fs.rmSync('../includes/icons', { recursive: true });

  const convert = (source, destination) => {
    return src(source)
      .pipe(
        rename((path) => {
          path.dirname = path.dirname.toLowerCase();
          path.basename = path.basename.toLowerCase();
        })
      )
      .pipe(
        cheerio(($) => {
          $('svg').each((index, svg) => {
            $(svg).removeAttr('class');
          });
        })
      )
      .pipe(dest(destination));
  };

  return mergeStream(
    convert(
      './node_modules/@fortawesome/fontawesome-free/svgs/**/*.svg',
      '../includes/icons/fontawesome-free'
    ),
    convert(
      './node_modules/ionicons/dist/svg/*.svg',
      '../includes/icons/ion-icons'
    ),
    convert(
      './node_modules/bootstrap-icons/icons/*.svg',
      '../includes/icons/bootstrap-icons'
    ),
    convert(
      './node_modules/boxicons/svg/**/*.svg',
      '../includes/icons/box-icons'
    ),
    convert(
      './node_modules/feather-icons/dist/icons/*.svg',
      '../includes/icons/feather-icons'
    ),
    convert(
      './node_modules/flat-color-icons/svg/*.svg',
      '../includes/icons/flat-color-icons'
    ),
    convert(
      './node_modules/@primer/octicons/build/svg/*.svg',
      '../includes/icons/octicons'
    ),
    convert(
      './node_modules/grommet-icons/img/*.svg',
      '../includes/icons/grommet-icons'
    ),
    convert(
      './node_modules/heroicons/24/**/*.svg',
      '../includes/icons/heroicons'
    ),
    convert(
      './node_modules/@material-design-icons/svg/**/*.svg',
      '../includes/icons/material-design-icons'
    ),
    convert(
      './node_modules/remixicon/icons/**/*.svg',
      '../includes/icons/remix-icons'
    ),
    convert(
      './node_modules/simple-icons/icons/*.svg',
      '../includes/icons/simple-icons'
    ),
    convert(
      './node_modules/@tabler/icons/icons/*.svg',
      '../includes/icons/tabler-icons'
    ),
    convert(
      './node_modules/@vscode/codicons/src/icons/*.svg',
      '../includes/icons/vscode-icons'
    ),
    convert('./node_modules/css.gg/icons/svg/*.svg', '../includes/icons/css-gg')
  );
};

const mapIconsToJson = (done) => {
  const iconSets = fs.readdirSync('../includes/icons');
  for (const iconSet of iconSets) {
    const iconSetPath = `../includes/icons/${iconSet}`;
    if (iconSet === '.DS_Store' || iconSet.endsWith('.json')) {
      continue;
    }

    function recursivelyLoopThroughFolder(path) {
      const files = fs.readdirSync(path);
      for (const file of files) {
        const filePath = `${path}/${file}`;
        if (file === '.DS_Store' || file.endsWith('.json')) {
          continue;
        }
        if (fs.statSync(filePath).isDirectory()) {
          recursivelyLoopThroughFolder(filePath);
        } else {
          const icons = {};
          const iconSetIcons = fs.readdirSync(path);
          for (const icon of iconSetIcons) {
            const iconPath = `${path}/${icon}`;
            const iconContent = fs.readFileSync(iconPath);

            icons[icon] = iconContent.toString();
          }

          return fs.writeFileSync(
            `../includes/icons/${iconSet}${
              iconSetPath.endsWith(path)
                ? ''
                : `-${path.split('/').slice(-1).pop()}`
            }.json`,
            JSON.stringify(icons)
          );
        }
      }
    }

    recursivelyLoopThroughFolder(iconSetPath);
  }

  done();
};

const deleteIcons = (done) => {
  const iconsDirectory = path.join(__dirname, '../includes/icons');
  const items = fs.readdirSync(iconsDirectory);
  items.forEach((item) => {
    const itemPath = path.join(iconsDirectory, item);
    if (fs.statSync(itemPath).isDirectory()) {
      fs.rmSync(itemPath, { recursive: true, force: true });
    }
  });

  done();
};

const getBlockSchema = (done) => {
  fetch('https://app.blockstudio.dev/schema')
    .then((e) => e.json())
    .then((e) => {
      fs.unlink('../includes/schemas/block.json', () => {});
      fs.appendFile(
        '../includes/schemas/block.json',
        JSON.stringify(e),
        () => {}
      );
      done();
    });
};

const getExtensionsSchema = (done) => {
  fetch('https://app.blockstudio.dev/schema/extend')
    .then((e) => e.json())
    .then((e) => {
      fs.unlink('../includes/schemas/extensions.json', () => {});
      fs.appendFile(
        '../includes/schemas/extensions.json',
        JSON.stringify(e),
        () => {}
      );
      done();
    });
};

const getBlockstudioSchema = (done) => {
  fetch('https://app.blockstudio.dev/schema/blockstudio')
    .then((e) => e.json())
    .then((e) => {
      fs.unlink('../includes/schemas/blockstudio.json', () => {});
      fs.appendFile(
        '../includes/schemas/blockstudio.json',
        JSON.stringify(e),
        () => {}
      );
      done();
    });
};

const getDocs = (done) => {
  fetch('https://blockstudio.dev/documentation/llm.txt')
    .then((e) => e.text())
    .then((e) => {
      fs.unlink('../includes/llm/blockstudio.md', () => {});
      fs.appendFile('../includes/llm/blockstudio.md', e, () => {});
      done();
    });
};

const getTailwind = (done) => {
  fetch('https://cdn.tailwindcss.com')
    .then((e) => e.text())
    .then((e) => {
      fs.unlink('../includes/admin/assets/tailwind/cdn.js', () => {});
      fs.appendFile('../includes/admin/assets/tailwind/cdn.js', e, () => {});
      done();
    });
};

const getTailwindClasses = (done) => {
  const cssPath = './src/tailwind/data/classes/output.css';
  const outputPath = './src/tailwind/data/classes/index.ts';
  const screensPath = './src/tailwind/data/classes/screens.ts';
  const screens = require('tailwindcss/defaultTheme').screens;

  fs.writeFile(
    screensPath,
    `const screens = ${JSON.stringify(screens)}; export default screens;`,
    (writeErr) => {
      if (writeErr) {
        console.error('Failed to write file:', writeErr);
      }
    }
  );

  fs.readFile(cssPath, (err, css) => {
    if (err) {
      console.error('Failed to read file:', err);
      done();
      return;
    }

    const classNames = [];

    postcss.parse(css).walkRules((rule) => {
      const selector = rule.selector;
      const classRegex = /\.([^\s\.:]+[^\s,{]*)/g;
      let match;

      while ((match = classRegex.exec(selector))) {
        if (!match[1].includes('::') && !match[1].includes('\\/')) {
          classNames.push(match[1]);
        }
      }
    });

    function isColorClass(className) {
      return /-\d{1,3}$/.test(className);
    }

    const colorClasses = classNames.filter(isColorClass);
    colorClasses.sort((a, b) => {
      const baseA = a.substring(0, a.lastIndexOf('-'));
      const baseB = b.substring(0, b.lastIndexOf('-'));
      if (baseA === baseB) {
        const numA = parseInt(a.split('-').pop(), 10);
        const numB = parseInt(b.split('-').pop(), 10);
        return numA - numB;
      }
      return 0;
    });

    const sortedClassNames = classNames.map((className) =>
      isColorClass(className) ? colorClasses.shift() : className
    );

    fs.writeFile(
      outputPath,
      `const classes = ['${sortedClassNames.join(
        "','"
      )}']; export default classes;`,
      (writeErr) => {
        if (writeErr) {
          console.error('Failed to write file:', writeErr);
        }
        done();
      }
    );
  });
};

const getTailwindPreflight = (done) => {
  const cssPath = './src/tailwind/data/preflight/output.css';
  const outputPath = '../includes/admin/assets/tailwind/preflight.css';

  fs.readFile(cssPath, (readErr, css) => {
    if (readErr) {
      console.error('Failed to read file:', readErr);
      done();
      return;
    }

    const scopePreflight = {
      postcssPlugin: 'scope-preflight',
      Once(root) {
        root.walkRules((rule) => {
          let selectors = rule.selector
            .split(',')
            .map((selector) => selector.trim());

          const matchSelectors = new Set([
            'blockquote',
            'dl',
            'dd',
            'h1',
            'h2',
            'h3',
            'h4',
            'h5',
            'h6',
            'hr',
            'figure',
            'p',
            'pre',
          ]);

          const isMatchingRule = selectors.every((selector) =>
            matchSelectors.has(selector)
          );

          if (isMatchingRule) {
            rule.remove();
            root.append({
              selector:
                '*:not([class*="mt-"]):not([class*="mr-"]):not([class*="mb-"]):not([class*="ml-"]):not([class*="m-"])',
              nodes: [
                {
                  prop: 'margin',
                  value: '0',
                },
              ],
            });
          } else {
            selectors = selectors.filter((selector) => {
              return !(selector === 'html' || selector === ':host');
            });

            if (selectors.length === 0) {
              rule.remove();
            } else {
              selectors = selectors
                .map((selector) => {
                  if (/^h[1-6]$/.test(selector)) {
                    let fontProps = rule.nodes.filter(
                      (node) =>
                        (node.prop === 'font-size' &&
                          node.value === 'inherit') ||
                        (node.prop === 'font-weight' &&
                          node.value === 'inherit')
                    );
                    if (fontProps.length === rule.nodes.length) {
                      return null;
                    }
                  }
                  return `[data-blockstudio] ${selector}`;
                })
                .filter(Boolean);

              if (selectors.length === 0) {
                rule.remove();
              } else {
                selectors = [...new Set(selectors)];
                rule.selector = selectors.join(', ');
              }
            }
          }
        });
      },
    };

    const nanoOpts = {
      preset: ['default'],
    };

    postcss([scopePreflight, cssnano(nanoOpts)])
      .process(css, { from: undefined })
      .then((result) => {
        fs.writeFile(outputPath, result.css, (writeErr) => {
          if (writeErr) {
            console.error('Failed to write file:', writeErr);
            done();
            return;
          }
          done();
        });
      })
      .catch((processErr) => {
        console.error('Failed to process CSS:', processErr);
        done();
      });
  });
};

const copyInteractivityAssets = (done) => {
  const outputBaseDir = path.resolve(
    __dirname,
    '../includes/assets/interactivity'
  );
  fs.rmSync(outputBaseDir, { recursive: true, force: true });
  fs.mkdirSync(outputBaseDir, { recursive: true });

  const assetsToCopy = [
    { type: 'directory', path: '@wordpress/interactivity/build-module' },
    { type: 'file', path: 'preact/dist/preact.module.js' },
    { type: 'file', path: 'preact/hooks/dist/hooks.module.js' },
    { type: 'file', path: '@preact/signals/dist/signals.module.js' },
    { type: 'file', path: '@preact/signals-core/dist/signals-core.module.js' },
  ];

  const streams = [];

  for (const asset of assetsToCopy) {
    const sourceFullPath = path.resolve(__dirname, 'node_modules', asset.path);

    if (!fs.existsSync(sourceFullPath)) {
      console.warn(
        `[copyInteractivityAssets] Source not found: ${sourceFullPath}. Skipping.`
      );
      continue;
    }

    if (asset.type === 'directory') {
      const targetDestDir = path.resolve(outputBaseDir, asset.path);
      streams.push(
        src(path.join(sourceFullPath, '**/*'), {
          base: sourceFullPath,
          dot: true,
          allowEmpty: true,
        }).pipe(dest(targetDestDir))
      );
    } else if (asset.type === 'file') {
      const targetFileDir = path.resolve(
        outputBaseDir,
        path.dirname(asset.path)
      );
      streams.push(
        src(sourceFullPath, { allowEmpty: true }).pipe(dest(targetFileDir))
      );
    }
  }

  const processInteractivityImports = (callback) => {
    const wpInteractivityDir = path.resolve(
      outputBaseDir,
      '@wordpress/interactivity/build-module'
    );

    if (fs.existsSync(wpInteractivityDir)) {
      const processFile = (filePath) => {
        if (path.extname(filePath) === '.js') {
          let content = fs.readFileSync(filePath, 'utf8');
          const originalContent = content;

          const importExportRegex =
            /(from\s+|import\s*\(\s*|export\s+(?:\{[^}]*}\s*from\s+|\*\s*from\s+))('|")(\.\.?\/[^\'\"\?\s#]+(?<!\.(?:js|mjs|ts|tsx|jsx|json|css|sass|scss|less|html|svg|png|jpg|jpeg|gif|webp|xml|txt)))(\2)/g;
          content = content.replace(
            importExportRegex,
            (match, p1Prefix, p2Quote, p3PathWithoutExt, p4Quote) => {
              if (
                p3PathWithoutExt.endsWith('/proxies') ||
                p3PathWithoutExt === './proxies'
              ) {
                return `${p1Prefix}${p2Quote}${p3PathWithoutExt}/index.js${p4Quote}`;
              }
              return `${p1Prefix}${p2Quote}${p3PathWithoutExt}.js${p4Quote}`;
            }
          );

          if (content !== originalContent) {
            fs.writeFileSync(filePath, content, 'utf8');
          }
        }
      };

      const walkDir = (currentPath) => {
        fs.readdirSync(currentPath).forEach((entry) => {
          const fullPath = path.join(currentPath, entry);
          if (fs.statSync(fullPath).isDirectory()) {
            walkDir(fullPath);
          } else {
            processFile(fullPath);
          }
        });
      };

      try {
        walkDir(wpInteractivityDir);
      } catch (err) {
        console.error(
          `[copyInteractivityAssets] Error processing JS files in ${wpInteractivityDir}:`,
          err
        );
      }
    }
    callback(); // Proceed to Gulp's done callback
  };

  if (streams.length === 0) {
    console.log(
      '[copyInteractivityAssets] No valid source files or directories found to copy.'
    );
    processInteractivityImports(done); // Still call processing in case of manual pre-existing files, then done.
    return;
  }

  return mergeStream(streams).on('end', () => {
    processInteractivityImports(done);
  });
};

exports.icons = series(createIcons, mapIconsToJson, deleteIcons);
exports.schema = series(
  getBlockSchema,
  getExtensionsSchema,
  getBlockstudioSchema
);
exports.docs = getDocs;
exports.tailwind = series(getTailwind);
exports.tailwindClasses = series(getTailwindClasses);
exports.tailwindPreflight = series(getTailwindPreflight);
exports.interactivityAssets = copyInteractivityAssets;
