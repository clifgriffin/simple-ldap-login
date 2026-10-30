const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { spawnSync, spawn } = require( 'node:child_process' );
const gettext = require( 'gettext-parser' );

const report = ( message ) => process.stdout.write( `${ message }\n` );
const domain = 'simple-ldap-login';
const folder = path.resolve( __dirname, '../languages' );
const locales = [
	'ar',
	'cs_CZ',
	'da_DK',
	'de_DE',
	'en_US',
	'es_ES',
	'et',
	'fi',
	'fr_FR',
	'hi_IN',
	'it_IT',
	'ja',
	'ko_KR',
	'nb_NO',
	'nl_NL',
	'pl_PL',
	'pt_BR',
	'pt_PT',
	'ru_RU',
	'sv_SE',
	'th',
	'tr_TR',
	'zh_CN',
	'zh_TW',
];
const tool = path.resolve(
	__dirname,
	'../node_modules/gpt-po/lib/src/index.js'
);
const pot = path.join( folder, `${ domain }.pot` );
const entries = ( catalog ) =>
	Object.values( catalog.translations )
		.flatMap( Object.values )
		.filter( ( item ) => item.msgid );
const run = ( command, args ) => {
	const result = spawnSync( command, args, { encoding: 'utf8' } );
	if ( result.status !== 0 ) {
		throw new Error(
			result.stderr || result.stdout || result.error?.message
		);
	}
};

async function main() {
	const action = process.argv[ 2 ];
	if ( action === 'sync' ) {
		for ( const locale of locales ) {
			const file = path.join( folder, `${ domain }-${ locale }.po` );
			if ( fs.existsSync( file ) ) {
				run( process.execPath, [
					tool,
					'sync',
					'--po',
					file,
					'--pot',
					pot,
				] );
			} else {
				run( 'msginit', [
					'--no-translator',
					'--input',
					pot,
					'--locale',
					locale,
					'--output-file',
					file,
				] );
			}
			const catalog = gettext.po.parse( fs.readFileSync( file ) );
			catalog.headers.language = locale;
			catalog.headers[ 'language-team' ] = locale;
			catalog.headers[ 'last-translator' ] =
				locale === 'en_US' ? 'Simple LDAP Login' : 'gpt-po v1.2.3';
			if ( ! catalog.headers[ 'plural-forms' ] ) {
				let forms = 'nplurals=2; plural=(n != 1);';
				if (
					[ 'ja', 'ko_KR', 'th', 'zh_CN', 'zh_TW' ].includes( locale )
				) {
					forms = 'nplurals=1; plural=0;';
				} else if ( locale === 'ar' ) {
					forms =
						'nplurals=6; plural=(n==0 ? 0 : n==1 ? 1 : n==2 ? 2 : n%100>=3 && n%100<=10 ? 3 : n%100>=11 && n%100<=99 ? 4 : 5);';
				}
				catalog.headers[ 'plural-forms' ] = forms;
			}
			if ( locale === 'en_US' ) {
				entries( catalog ).forEach( ( item ) => {
					item.msgstr = [ item.msgid ];
				} );
			}
			// Keep names, URLs, and code identifiers unchanged in every locale.
			entries( catalog )
				.filter( ( item ) =>
					/^(https?:\/\/|Clif Griffin Development Inc\.$|Simple LDAP Login$)/.test(
						item.msgid
					)
				)
				.forEach( ( item ) => {
					item.msgstr = [ item.msgid ];
				} );
			fs.writeFileSync( file, gettext.po.compile( catalog ) );
		}
		report( `Updated ${ locales.length } PO catalogs.` );
	} else if ( action === 'translate' ) {
		if ( ! process.env.OPENAI_API_KEY ) {
			throw new Error( 'OPENAI_API_KEY is required.' );
		}
		const requested = process.argv.slice( 3 );
		if ( requested.some( ( locale ) => ! locales.includes( locale ) ) ) {
			throw new Error( 'Unknown locale.' );
		}
		const pending = ( requested.length ? requested : locales ).filter(
			( locale ) => locale !== 'en_US'
		);
		await Promise.all(
			Array.from( { length: 3 }, async () => {
				while ( pending.length ) {
					const locale = pending.shift();
					report( `Translating ${ locale }.` );
					await new Promise( ( resolve, reject ) => {
						const child = spawn(
							process.execPath,
							[
								tool,
								'translate',
								'--po',
								path.join(
									folder,
									`${ domain }-${ locale }.po`
								),
								'--source',
								'en',
								'--lang',
								locale,
								'--context',
								path.resolve(
									__dirname,
									'../.gpt-po/context.txt'
								),
							],
							{ stdio: [ 'ignore', 'ignore', 'pipe' ] }
						);
						let error = '';
						child.stderr.on( 'data', ( data ) => {
							error += data;
						} );
						child.on( 'error', reject );
						child.on( 'close', ( code ) =>
							code === 0
								? resolve()
								: reject(
										new Error( `${ locale }: ${ error }` )
									)
						);
					} );
					report( `Finished ${ locale }.` );
				}
			} )
		);
	} else if ( action === 'check' ) {
		const source = gettext.po.parse( fs.readFileSync( pot ) );
		const originals = entries( source );
		for ( const locale of locales ) {
			const file = path.join( folder, `${ domain }-${ locale }.po` );
			const catalog = gettext.po.parse( fs.readFileSync( file ) );
			const compiled = gettext.mo.parse(
				fs.readFileSync( file.replace( /\.po$/, '.mo' ) )
			);
			run( 'msgfmt', [
				'--check',
				'--check-format',
				'--output-file=/dev/null',
				file,
			] );
			for ( const item of originals ) {
				const translated =
					catalog.translations[ item.msgctxt || '' ]?.[ item.msgid ]
						?.msgstr[ 0 ];
				if ( ! translated ) {
					throw new Error(
						`${ locale }: missing translation for ${ item.msgid }`
					);
				}
				if (
					compiled.translations[ item.msgctxt || '' ]?.[ item.msgid ]
						?.msgstr[ 0 ] !== translated
				) {
					throw new Error( `${ locale }: rebuild the MO catalog.` );
				}
				const placeholders = ( value ) =>
					( value.match( /%(?:\d+\$)?[sdf]/g ) || [] ).sort().join();
				if (
					placeholders( translated ) !== placeholders( item.msgid )
				) {
					throw new Error(
						`${ locale }: changed placeholder in ${ item.msgid }`
					);
				}
			}
		}
		report(
			`Translation checks passed: ${ locales.length } locales, ${ originals.length } strings each.`
		);
	} else {
		throw new Error( 'Use sync, translate, or check.' );
	}
}
main().catch( ( error ) => {
	process.stderr.write( `${ error.message }\n` );
	process.exitCode = 1;
} );
