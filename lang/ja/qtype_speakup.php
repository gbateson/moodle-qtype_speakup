<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Japanese language strings for qtype_speakup.
 *
 * The recorder widget strings follow qtype_recordrtc (The Open University, GPL v3+).
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addmoreitem1'] = '目標項目を1件追加する';
$string['addmoreitems'] = '目標項目を{$a}件追加する';
$string['addmorephrase1'] = '目標フレーズを1件追加する';
$string['addmorephrases'] = '目標フレーズを{$a}件追加する';
$string['aiassessment'] = 'AI評価を使用する';
$string['aiassessment_help'] = 'チェックすると、解答は下記のルーブリックとプロバイダを使って、AIによっても評価されます。評価は解答の送信後、バックグラウンドで行われます。AIのフィードバックは評定コメントとして表示され、AIのスコアは下記の重みに従って評点に加算されます。';
$string['aiassessmentfailed'] = 'あなたの解答を処理できませんでした。教師が手動で評定します。';
$string['aiassessmentfailedreason'] = 'この解答は評価できなかったため、手動で評定する必要があります。理由: {$a}';
$string['aiassessmentpending'] = 'あなたの解答は評価待ちです。評点とフィードバックの準備ができ次第、ここに表示されます。';
$string['aiassessmentpendingnote'] = '通常は数分かかります。準備ができると、このページが更新されます。';
$string['aiassessmentready'] = '評点とフィードバックの準備ができました。下に引いてページを更新すると表示されます。';
$string['aiassessmentretrying'] = '解答の評価に失敗しました。後でもう一度試みます。理由: {$a}';
$string['aiaudioonly'] = '動画の音声のみをAIに送信する';
$string['aiaudioonly_help'] = 'チェックすると、動画の解答の音声のみがAIプロバイダに送信され、文字起こしと評価が行われます。この方が速く、費用も少なく、音声は受け付けるが動画は受け付けないプロバイダでも使えます。学生が話す内容のみを評価し、動画は誰が話しているかを示すためだけにある場合に使用してください。

ジェスチャーやアイコンタクトなど、AIが目で見て分かることも評価させたい場合は、チェックを外してください。

教師には常に動画全体が表示されます。音声を取り出すには、サーバにffmpegが必要です (SpeakUpのサイト設定を参照)。ffmpegがない場合は、動画全体が送信されます。';
$string['aipolicylink'] = 'AIポリシーを読んで同意';
$string['aipolicynotice'] = 'あなたの解答は、評価または文字起こしのためにAIサービスに送信されます。まず{$a}してください。同意するまで解答は送信されず、代わりに教師が評定します。';
$string['aipromptinstructions'] = '上記の指示に従って、この解答を100点満点で評価してください。録音・録画または文字起こしが実際には課題に取り組んでいない場合 (例えば、無音である、課題と関係がない、学生が課題に取り組まずに課題そのものについて質問している、など) でも、それを反映したスコア (通常は0、部分的に本当に取り組んでいる場合は低いスコア) を付け、その理由をフィードバックで説明してください。スコアを省略してはいけません。説明のフィードバックの後、返答の最後に、正確に「SCORE: N」と書いた行を付けてください。Nは0から100までの整数です。この行は、例外なく、すべての返答に必須です。';
$string['aipromptoverlong'] = '注意: 録音・録画の長さは{$a->duration}秒ですが、この課題の制限時間は{$a->limit}秒でした。';
$string['aiprompttranscribe'] = 'まず、録音 (または動画の音声トラック) で学生が話している内容を、そのまま正確に文字起こししてください。学生は{$a}で話すことになっています。文法、語彙、発音の誤りは訂正しないでください。学生が実際に話した内容を、句読点を付けて書いてください。返答の最初に、正確に「TRANSCRIPT:」と書いた行を置き、その後に文字起こしを続けてください。次に、正確に「FEEDBACK:」と書いた行を置き、その後にフィードバックを続けてください。';
$string['aiprompttranscribeimage'] = '学生の解答は、添付ファイルにある手書き (または手描き) の答案です。添付ファイルは写真、スキャン、PDFのいずれかで、1ページまたは複数ページです。学生は{$a}で書くことになっています。まず、学生が書いた内容を、「Page 1:」のような見出しの下に、ページごとにそのまま正確に文字起こししてください。綴り、文法、語彙の誤りは訂正しないでください。学生が実際に書いた内容を書いてください。絵や図があれば、[角括弧] の中に簡単に説明してください。読めない語は [unclear] と書いてください。返答の最初に、正確に「TRANSCRIPT:」と書いた行を置き、その後に文字起こしを続けてください。次に、正確に「FEEDBACK:」と書いた行を置き、その後にフィードバックを続けてください。';
$string['aiprompttranscribeonly'] = 'この録音 (またはこの動画の音声トラック) で話者が話している内容を、そのまま正確に文字起こししてください。話者は{$a}で話すことになっています。文法、語彙、発音の誤りは訂正しないでください。実際に話された内容を、句読点を付けて書いてください。文字起こしのみを返答してください。';
$string['aiprompttranscribeonlyimage'] = '添付ファイルに書かれている内容を、「Page 1:」のような見出しの下に、ページごとにそのまま正確に文字起こししてください。添付ファイルは手書きの写真、スキャン、PDFのいずれかで、1ページまたは複数ページです。書き手は{$a}で書くことになっています。綴り、文法、語彙の誤りは訂正しないでください。実際に書かれている内容を書いてください。絵や図があれば、[角括弧] の中に簡単に説明してください。読めない語は [unclear] と書いてください。文字起こしのみを返答してください。';
$string['aiprompttranscriptlabel'] = '学生の話した解答の文字起こし:';
$string['aiproviderauto'] = '自動';
$string['aiproviderid'] = 'AIプロバイダ';
$string['aiproviderid_help'] = 'この問題の解答を評価するAIプロバイダです。プロバイダとそのAPIキーは、サイトまたは教師が自分のコース用に、AIブリッジで設定します。ここでは選択するだけです。「自動」を選択すると、AIブリッジが選択します。サイトがMoodle自体のAIを設定している場合はそれを、そうでない場合はここで共有されているプロバイダを、近いものから順に使用します。

解答は学生のために評価されるため、問題がある場所で共有されているプロバイダのみが表示され、個人用のプロバイダは表示されません。';
$string['aiprovidersheading'] = 'AIプロバイダ';
$string['aiprovidersheading_desc'] = 'SpeakUpは、解答と音声プロンプトを、AIブリッジ (tool_aibridge) を通してAIプロバイダに送信します。プロバイダとそのAPIキーは、サイトまたは教師が自分のコース用に、AIブリッジで設定します。AIポリシー、利用回数の制限、AIリクエストのログもAIブリッジにあります。';
$string['airubric'] = 'AI評価のルーブリック / 指示';
$string['airubric_help'] = '文字起こしと一緒にAI評価エンジンに送信される指示です。例えば、流暢さ、文法、語彙、発音のどの面を評価するか、どのように採点するかを書きます。

問題テキストの全部または一部を、プレーンテキストとして、次のプレースホルダで含めることができます:

* "{{questiontext}}" - 問題テキスト全体
* "{{questiontext.rubric}}" - class="rubric" を持つ最初の要素
* "{{questiontext#task}}" - id="task" を持つ要素
* "{{questiontext div.rubric}}" - class="rubric" を持つ最初の "div"

複数の問題で1つのルーブリックを共有するには、別の問題 (例えば、小テストの最初にある「説明」問題) に一度だけ書き、その問題にIDナンバー (例えば "quickfire-rubric") を付けます。次に、IDナンバーとスラッシュを前に付けます。例えば "{{quickfire-rubric/questiontext.rubric}}"、問題テキスト全体の場合は "{{quickfire-rubric/questiontext}}" です。その問題は、この問題のカテゴリ、その上のカテゴリ、というように順に探されるため、親カテゴリにあるルーブリックは、そのサブカテゴリのすべての問題で使用できます。その問題の最新の「準備完了」バージョンが使用されるため、変更はそれ以降に評価される解答に適用されます。

問題の設定を、言葉で含めることもできます:

* "{{questionname}}" - 問題名
* "{{audioprompt}}" - 音声プロンプトの文字起こし
* "{{timelimit}}" - 例: "30 seconds"
* "{{promptplays}}" - 例: "once"、"twice"
* "{{responses}}" - 例: "1 response"
* "{{language}}" - 例: "English (en-US)"
* "{{target.words}}" - 語数の最も高い目標値。例: "30 words" (他の項目タイプも使用できます。例: "{{target.longwords}}")

GIFTで使われる名前も使用できます (例: "{{AUDIO}}"、"{{TIMELIMIT}}")。大文字と小文字は区別されません。共有ルーブリックでは、これらはそれぞれの問題の設定で埋められます。

すべての学生が同じルーブリックで評定されるように、多言語コンテンツ (lang="xx") は英語でAIに送信されます。別の言語にするには、コロンの後に追加します。例: "{{questiontext.rubric:ja}}"。&lt;summary&gt; のラベルとメディアプレーヤーは除かれます。問題テキストの一部を学生に見せないようにするには、"d-none" クラスを持つ要素に入れます。例: &lt;div class="rubric d-none"&gt;...&lt;/div&gt;。';
$string['aisettings'] = 'AI評価設定';
$string['aisettings_help'] = '自動評定の一部として、AIで解答を評価するための設定です。';
$string['aiweighting'] = 'AIの重み';
$string['aiweighting_help'] = 'AI評価によって与えられる、問題評点の割合です。例えば、重みが50%の場合、AIのスコアが80であれば、評点に40%が加算されます。重みが0%の場合、AIのフィードバックは表示されますが、評点には影響しません。';
$string['assessmentdonemessage'] = '小テスト「{$a->quiz}」の問題「{$a->question}」に対するあなたの解答が評価されました。受験を見直して、評点とフィードバックを確認してください: {$a->url}';
$string['assessmentdonesubject'] = 'あなたの解答が評価されました';
$string['assessmentfailedmessage'] = '小テスト「{$a->quiz}」の問題「{$a->question}」に対する{$a->student}さんの解答は評価できなかったため、手動で評定する必要があります。

理由: {$a->reason}

ここで評定してください: {$a->url}';
$string['assessmentfailedpreviewmessage'] = '問題「{$a->question}」のプレビューを評価できませんでした。

理由: {$a->reason}';
$string['assessmentfailedsubject'] = 'SpeakUpの解答を評価できませんでした';
$string['audioprompt'] = '音声プロンプト (任意)';
$string['audioprompt_help'] = '学生が、問題テキストの代わりに、または問題テキストと一緒に聞く、任意の音声プロンプトです。ここで録音するか、音声ファイルをアップロードしてください。問題テキストの下に音声プレーヤーとして表示されます。';
$string['audiopromptdeleted'] = '音声プロンプトを削除しました。';
$string['audiopromptfailedmessage'] = '問題「{$a->question}」の音声プロンプトを、その文字起こしから生成できませんでした。問題の編集ページで録音またはアップロードするか、問題をもう一度保存して再試行してください。

理由: {$a->reason}';
$string['audiopromptfailedsubject'] = 'SpeakUpの音声プロンプトを生成できませんでした';
$string['audiopromptgeneratenote'] = '問題を保存するときに音声プロンプトがない場合は、この文字起こしから生成されます。';
$string['audiopromptnotready'] = '音声プロンプトを準備中です。数分後にもう一度お試しください。';
$string['audiopromptpending'] = '音声プロンプトをこの文字起こしから生成しています。数分で準備ができるはずです。';
$string['autorecord'] = 'プロンプトが終わったら録音を開始する';
$string['autorecord_help'] = 'チェックすると、プロンプトの再生が終わるとすぐに録音が始まるため、学生はすぐに解答しなければなりません。プロンプトとは、上記の音声プロンプトがある場合はそれ、ない場合は問題テキストの最初の音声プレーヤーです。学生は、プロンプトが終わる前に自分で録音を始めることもできます。';
$string['autorecordnote'] = 'プロンプトが終わるとすぐに録音が始まります。';
$string['awaitingaiassessment'] = 'あなたの録音は文字起こしされ、評価のために送信されました。';
$string['awaitingtranscription'] = 'あなたの録音を受け取り、処理しています。1、2分かかることがあります。';
$string['browsertranscript'] = 'ライブ文字起こし (ブラウザの音声認識による)';
$string['calccapped'] = 'これは100%を超えるため、100%に減らしました。';
$string['calcgrade'] = 'したがって、この解答の自動評点は{$a->grade} ({$a->maxgrade}の{$a->percent}%) になりました。';
$string['calcholistic'] = '解答全体の質に対して{$a->percent}% (100点中{$a->score}点、重み{$a->weighting}%)';
$string['calcitemfirst'] = '最初の{$a->amount}に対して{$a->percent}%';
$string['calcitemfirstpart'] = '最初の{$a->amount}のうち{$a->progress}に対して{$a->percent}% ({$a->progress}/{$a->width} × {$a->award}%)';
$string['calcitemnext'] = '次の{$a->amount}に対して{$a->percent}%';
$string['calcitemnextpart'] = '次の{$a->amount}のうち{$a->progress}に対して{$a->percent}% ({$a->progress}/{$a->width} × {$a->award}%)';
$string['calcitemreached'] = '{$a->amount}に達したことに対して{$a->percent}%';
$string['calcitemreachedpart'] = '{$a->amount}に対して{$a->percent}% ({$a->progress}/{$a->width} × {$a->award}%)';
$string['calcmaxgrade'] = 'この問題の最高評点は{$a}です。';
$string['calcoverride'] = 'その後、{$a->date}に、評点は手動で{$a->grade}に変更されました。';
$string['calcpenalty'] = 'ただし、音声プロンプトの文字起こしを表示したため、{$a}%が減点されました。';
$string['calcphrase'] = '「{$a->phrase}」を使用したことに対して{$a->percent}%';
$string['calcrawpercent'] = 'この解答の素点の割合は{$a}%です。';
$string['calcrawpercentdetails'] = 'この解答の素点の割合は{$a->percent}%です。<br>= {$a->details}';
$string['calculation'] = '評点の計算';
$string['confirmtranscript'] = '文字起こしを表示すると、評点が{$a}%減点されます。本当に表示しますか?';
$string['confirmtranscripttitle'] = '文字起こしを表示しますか?';
$string['convertvideos'] = '動画をMP4に変換する';
$string['convertvideos_desc'] = 'チェックすると、MP4ではない学生の動画 (例えば、Chrome、Edge、Firefoxで録画したWebM動画) は、サイズに関係なく、小テストの送信後にバックグラウンドでMP4に変換されます。iPhoneとiPadは、ブラウザでもMoodleアプリでもWebM動画を再生できませんが、MP4は再生できます。これにはffmpegが必要です (上記を参照)。';
$string['detailsband'] = '評点帯';
$string['detailsgrade'] = '評点';
$string['detailsitem'] = '項目';
$string['detailspercentof'] = '{$a->percent}% ({$a->max}%中)';
$string['detailsphrase'] = '目標フレーズ';
$string['detailsprogress'] = '{$a->progress} / {$a->width}';
$string['detailsrange'] = '{$a->lower} – {$a->upper}';
$string['detailsresponse'] = 'あなたの解答';
$string['detailsresult'] = '結果';
$string['detailsstatistic'] = '統計';
$string['detailsvalue'] = '値';
$string['divisor1'] = '丁度';
$string['divisorn'] = '{$a}で割った値';
$string['downloadrecording'] = '{$a}をダウンロードする';
$string['durationtoolong'] = 'この録音・録画の長さは{$a->duration}秒です。制限時間は{$a->limit}秒です。';
$string['err_compatibility'] = 'iOS/iPadOSデバイスはオープンな形式で動画を録画できますが、現在のところ再生することはできません。Appleがこれを修正するまで、この動画を見るには別のデバイスを使用してください。';
$string['err_invalidregex'] = 'これは有効な正規表現ではないようです: {$a}';
$string['err_itemduplicate'] = 'この項目には、すでに{$a}という目標値があります。同じ項目の目標値は、それぞれ異なる必要があります。';
$string['err_itemneedsai'] = 'この項目は文の数を数えるため、句読点のある文字起こしが必要です。ブラウザのライブ文字起こしには句読点がないため、AI評価または「アップロード」を使用するか、別の項目を選択してください。';
$string['err_itempercent'] = 'この項目の割合を選択してください。';
$string['err_itemtarget'] = 'この項目に、0より大きい目標値を入力してください。';
$string['err_phrasebrackets'] = 'このフレーズの「(」にはそれぞれ後に「)」が、「)」にはそれぞれ前に「(」が必要です。';
$string['err_phraseoperator'] = 'OR、AND、カンマの両側には語が必要です。例: "Then OR Next"、"First AND Finally"。';
$string['err_phrasepercent'] = 'このフレーズの割合を選択してください。';
$string['err_presetexists'] = 'そこには、すでにこの省略名のプリセットがあります: 「{$a}」。別の省略名を選択するか、「同じ省略名のプリセットを置き換える」をチェックしてください。';
$string['err_presetexistsrename'] = 'そこには、すでにこの省略名のプリセットがあります: 「{$a}」。別の省略名を選択してください。';
$string['err_presetshortname'] = '半角の小文字、数字、「-」、「_」を使用し、小文字または数字で始めてください。例: instant-response';
$string['err_rubricplaceholders'] = '次のプレースホルダは埋めることができません: {$a}。綴りを確認し、{{target...}} の各項目タイプについて、問題に目標値があることを確認してください。';
$string['err_rubricquestion'] = 'この問題のカテゴリにも、その上のカテゴリにも、IDナンバー「{$a}」の問題はありません。';
$string['err_speechrateimage'] = '話す速さは、画像の解答には使用できません。';
$string['err_uploadneedsmultimodal'] = 'アップロードされたファイルにはライブ文字起こしがないため、音声または動画ファイルそのものを受け取るAIプロバイダが必要です。別のプロバイダか、「録音」を選択してください。';
$string['err_uploadneedsprovider'] = 'アップロードされたファイルを文字起こしするには、ファイルそのものを受け取るAIプロバイダが必要ですが、ここでは共有されていません。「録音」または「手動」評定を選択するか、AIブリッジでプロバイダを追加してください。';
$string['erroraiprovider'] = 'AIプロバイダがエラーを返しました: {$a}';
$string['erroraiscoreunparseable'] = 'AIは、期待された形式でスコアを返しませんでした。';
$string['errornoproviders'] = 'ここでは、このリクエストを処理できるAIプロバイダがありません。このコース (またはサイト全体) でプロバイダが共有されていること、また解答がアップロードの場合は、そのプロバイダがファイルを受け取れることを確認してください。';
$string['errornotimelimit'] = '0より大きい制限時間を設定する必要があります。';
$string['errornotranscript'] = '評価できる文字起こしがありません (学生のブラウザがライブ文字起こしに対応していなかった可能性があります)。';
$string['errorpolicynotaccepted'] = '学生がAIポリシーに同意していないため、解答はAIに送信されませんでした。';
$string['errorproviderconfig'] = 'この問題にはAIプロバイダが設定されていないか、設定されたプロバイダが見つかりませんでした。';
$string['errorrubricquestion'] = 'AIルーブリックはIDナンバー「{$a}」の問題の問題テキストを使用していますが、この問題のカテゴリにも、その上のカテゴリにも、そのような問題はありません。';
$string['fileuploaded'] = 'ファイルをアップロードしました。';
$string['giftarchetypenotspeakup'] = 'ARCHETYPE={$a}: その問題はSpeakUp問題ではないため、その設定は使用されませんでした。';
$string['giftbadparts'] = '認識できません: {$a}';
$string['giftbadvalue'] = '{$a->name}={$a->value} ({$a->error})';
$string['giftexpectarchetype'] = 'SpeakUp問題のIDナンバーが必要です。例: quickfire-archetype';
$string['giftexpectlanguage'] = '言語コードが必要です。例: en-US';
$string['giftexpectnumber'] = '{$a->min}から{$a->max}までの整数が必要です';
$string['giftexpectoneof'] = '次のいずれかが必要です: {$a}';
$string['giftexpectpreset'] = 'プリセットの省略名が必要です。例: instant-response';
$string['giftexpecttargetitem'] = '(項目タイプ, 目標値, 割合%, /割る数) が必要です。例: (words, 50, 40%, /1)';
$string['giftexpecttargetphrase'] = '("フレーズ", 割合%, /割る数, full|partial, case|nocase, "ラベル") が必要です。例: ("I have played", 10%, /1, partial, nocase) または ("Then OR Next", 5%)。ラベルは任意です';
$string['giftexpectyesno'] = 'yes または no が必要です';
$string['giftnearestvalue'] = '使用できないため、{$a}を使用しました';
$string['giftnoarchetype'] = 'ARCHETYPE={$a}: このカテゴリにも、その上のカテゴリにも、このIDナンバーの問題がないため、通常のデフォルト設定を使用しました。同じGIFTファイル内のアーキタイプは、それを使用する問題より前に置く必要があります。';
$string['giftnoaudioprompt'] = 'この問題には音声プロンプトのファイルがありますが、文字起こしがないため、音声プロンプトは含まれません。';
$string['giftnoprovider'] = 'このサイトには「{$a}」というAIプロバイダはありません';
$string['giftunknownline'] = 'この行は認識できなかったため、スキップしました: {$a}';
$string['giftunknownsetting'] = 'この設定は認識できなかったため、スキップしました: {$a}';
$string['giftwarning'] = 'SpeakUp問題「{$a->name}」: {$a->message}';
$string['gradeitnow'] = '今すぐ評定する';
$string['gradingheader'] = '評定';
$string['gradingmethod'] = '評定方法';
$string['gradingmethod_auto'] = '自動';
$string['gradingmethod_help'] = '解答の評定方法:

* **自動** - 評点は、解答の文字起こしで見つかった目標フレーズと、(使用する場合は) AI評価のスコアに重みを掛けたものから成ります。合計の上限は100%です。後から教師が評点を変更することもできます。
* **手動** - 教師が聞いて手動で評定します。自動評定は行われません。';
$string['gradingmethod_manual'] = '手動';
$string['gumabort'] = '何らかの問題が発生したため、ウェブカメラ/マイクを使用できませんでした';
$string['gumabort_title'] = '問題が発生しました';
$string['gumnotallowed'] = 'ブラウザにウェブカメラ/マイクへのアクセスを許可する必要があります';
$string['gumnotallowed_title'] = '許可が正しくありません';
$string['gumnotfound'] = '入力デバイスが接続されていないか、有効になっていません';
$string['gumnotfound_title'] = 'デバイスがありません';
$string['gumnotreadable'] = '何かがブラウザによるウェブカメラ/マイクへのアクセスを妨げています';
$string['gumnotreadable_title'] = 'ハードウェアエラー';
$string['gumnotsupported'] = 'お使いのブラウザは、安全でない接続での録音に対応していないため、プラグインを終了する必要があります';
$string['gumnotsupported_title'] = '安全でない接続には対応していません';
$string['gumoverconstrained'] = '現在のウェブカメラ/マイクでは、必要な条件を満たすストリームを生成できません';
$string['gumoverconstrained_title'] = '条件に問題があります';
$string['gumsecurity'] = 'お使いのブラウザは、安全でない接続での録音に対応していないため、プラグインを終了する必要があります';
$string['gumsecurity_title'] = '安全でない接続には対応していません';
$string['gumtype'] = 'ウェブカメラ/マイクからストリームを取得しようとしましたが、条件が指定されていませんでした';
$string['gumtype_title'] = '条件が指定されていません';
$string['hintitem'] = '次回は{$a}を目指しましょう。';
$string['hintphrase'] = '次の表現を使ってみましょう: {$a}';
$string['hintphrases'] = 'すべての目標フレーズを使ってみましょう。';
$string['hintsheader'] = '評点を上げるためのヒント';
$string['imageshrunk'] = '「{$a->name}」はアップロードするには大きすぎた ({$a->before}) ため、小さくしました ({$a->after})。';
$string['imagetoobig'] = '「{$a->name}」は{$a->size}あり、アップロードできるほど小さくできませんでした。アップロードできる最大のファイルサイズは{$a->limit}です。';
$string['importnopreset'] = 'プリセット「{$a}」: ここで使用できる、この省略名のプリセットがないため、その設定は使用されませんでした。';
$string['insecurewarning'] = 'HTTPSで使用しない限り、お使いのブラウザではこのプラグインは動作しません。';
$string['insecurewarningtitle'] = '安全でない接続';
$string['itemaward'] = '目標項目 [{no}] の配点';
$string['itemchars'] = '文字数';
$string['itemcharspersentence'] = '1文あたりの文字数';
$string['itemfogindex'] = 'フォグ指数';
$string['itemgroup'] = '目標項目 [{no}]';
$string['itemgroup_help'] = 'この項目の配点は、割合を指定した数で割った値です。解答が目標値に達すると、満点が与えられます。目標値未満の場合は、値に比例した配点になります。例えば、「目標項目 [語数] の目標値は [100]。問題評点の最大 [20%] [丁度] を与えます。」の場合、50語で10%、75語で15%、100語以上で20%が与えられます。「2で割った値」にすると、これらがそれぞれ半分になります。割る数を使うと、整数でない配点を作れます。例えば、100%を3で割った値は33.33%です。

同じ項目に複数の目標値を設定すると、「小論文 (自動評定)」問題タイプと同じように、評点帯として機能します。各評点帯は、前の目標値 (または0) から自身の目標値までの範囲で、解答がその範囲をどれだけ満たしたかに比例して、その割合を与えます。例えば、「目標項目 [語数] の目標値は [100]。最大 [15%]」と「目標項目 [語数] の目標値は [130]。最大 [5%]」の場合:

* 83語は 15% × 83/100 = 12.5%
* 106語は 15% + 5% × 6/30 = 16%
* 130語以上は 15% + 5% = 20%

すべての目標項目の配点は、目標フレーズとAI評価 (使用する場合) と合わせて合計され、合計の上限は100%です。';
$string['itemlexicaldensity'] = '語彙密度 (%)';
$string['itemlongwords'] = '長い語';
$string['itemlongwordspersentence'] = '1文あたりの長い語';
$string['itemparagraphs'] = '段落数';
$string['itemscore'] = '{$a->item}: {$a->value} (問題評点の{$a->percent}%)。';
$string['itemsentences'] = '文の数';
$string['itemsentencesperparagraph'] = '1段落あたりの文の数';
$string['itemsheader'] = '目標項目';
$string['itemsheader_help'] = '目標項目は、学生がどれだけ話したか (例えば語数や話す速さ) に対して、問題評点の一部を与えます。解答の文字起こしで数えられます。';
$string['itemspeechrate'] = '話す速さ (1分あたりの語数)';
$string['itemtext1'] = '目標項目';
$string['itemtext2'] = 'の目標値は';
$string['itemtext3'] = '。';
$string['itemtext4'] = '問題評点の最大';
$string['itemtext5'] = 'を与えます。';
$string['itemuniquewords'] = '異なり語数';
$string['itemunknown'] = '不明';
$string['itemwords'] = '語数';
$string['itemwordspersentence'] = '1文あたりの語数';
$string['listenfirst'] = 'まずプロンプトを聞いてください。終わると録音が始まります。';
$string['manageaiproviders'] = 'AIプロバイダ (AIブリッジ)';
$string['managepresets'] = 'プリセットを管理する';
$string['maxfiles'] = '最大ファイル数';
$string['maxfiles_help'] = '画像の解答の場合: 学生がアップロードできるファイルの最大数です。例えば、手書きの答案の1ページにつき1ファイルです。PDFは、何ページあっても1ファイルと数えます。';
$string['maxfilesnote'] = '最大{$a}個のファイルをアップロードできます。例えば、1ページにつき1ファイルです。';
$string['maxpromptplays'] = 'プロンプトの再生回数';
$string['maxpromptplays_help'] = '学生がプロンプト (上記の音声プロンプト、ない場合は問題テキストの最初の音声プレーヤー) を再生できる回数です。一時停止して再開しても、1回の再生と数えます。';
$string['maxpromptplaysnote'] = 'プロンプトは{$a}回再生できます。';
$string['maxresponses'] = '解答できる回数';
$string['maxresponses_help'] = '学生が解答を録音 (またはアップロード) できる回数です。すべて使い切ると、それ以上録音できなくなります。';
$string['maxresponsesnote'] = '{$a}回解答できます。';
$string['mediatype'] = '解答のタイプ';
$string['mediatype_audio'] = '音声のみ';
$string['mediatype_help'] = '学生が解答として提出するもの:

* **音声のみ**: 話します。
* **音声 + 動画 (ウェブカメラ)**: カメラの前で話します。そのため、AI評価では、表情やジェスチャーなどの非言語コミュニケーションも考慮できます。
* **画像**: 手書きの答案や絵のスキャンや写真など、1つ以上の画像またはPDFをアップロードします。画像は常にアップロードで提出され、自動評定のためには、画像を読み取れるマルチモーダルなAIプロバイダが必要です。';
$string['mediatype_image'] = '画像 (例: 手書きのスキャン)';
$string['mediatype_video'] = '音声 + 動画 (ウェブカメラ)';
$string['messageprovider:assessmentdone'] = '評価が完了した、あなたのSpeakUpの解答';
$string['messageprovider:assessmentfailed'] = '評価できなかったSpeakUpの解答';
$string['messageprovider:audiopromptfailed'] = '生成できなかったSpeakUpの音声プロンプト';
$string['mobileapp'] = 'MoodleアプリでSpeakUp問題を許可する';
$string['mobileapp_desc'] = 'これがオンで、サイトが上記の条件を満たしている場合、学生はMoodleアプリでSpeakUp問題に解答できます。そうでない場合、アプリは、SpeakUp問題を含む小テストをブラウザで開くよう提案します。';
$string['mobilebadkey'] = 'このページはMoodleアプリからのみ開くことができます。問題に戻って、もう一度お試しください。';
$string['mobilecondition_framembedding'] = '「フレーム埋め込みを許可する」がオンになっている (サイト管理 > 一般 > セキュリティ > {$a})。';
$string['mobilecondition_mobileapp'] = '「MoodleアプリでSpeakUp問題を許可する」(下記) がオンになっている。';
$string['mobilecondition_mobileservice'] = 'モバイルデバイスのウェブサービスが有効になっている (サイト管理 > 一般 > {$a})。';
$string['mobileheading'] = 'Moodleアプリ';
$string['mobileheading_desc'] = 'Moodleアプリでは、学生はこのサイトのフレーム内でSpeakUp問題に解答するため、録音とアップロードはブラウザと同じように動作します。フレームにクッキーは不要です。アプリは1回限りのキーでフレームを開き、ファイルにはアプリ自身のログインを使用します。これには、次のすべてが必要です:';
$string['mobileloading'] = '読み込み中...';
$string['mobilenoattempt'] = 'この問題にはここでは解答できません。まだ進行中の、あなたの小テストの受験に含まれていません。';
$string['mobilenotsupported'] = 'これらのすべてが満たされていないため、Moodleアプリは、SpeakUp問題を含む小テストをブラウザで開くよう提案します。';
$string['mobilesupported'] = 'これらのすべてが満たされているため、学生はMoodleアプリ (バージョン4.2以降) でSpeakUp問題に解答できます。';
$string['name'] = '名称';
$string['nearingmaxsize'] = 'アップロードできるファイルの最大サイズに達しました';
$string['nearingmaxsize_title'] = '録音を停止しました';
$string['noaiproviders'] = 'ここではまだAIプロバイダが共有されていません。教師と管理者は、AIブリッジの<a href="{$a}">AIプロバイダ</a>ページで追加できます。';
$string['nofileuploaded'] = 'ファイルはアップロードされていません。';
$string['noitemtranscript'] = 'この解答には文字起こしがないため、目標項目を数えることができませんでした。';
$string['nopresets'] = 'ここにはまだプリセットがありません。追加するには、問題をプリセットとして保存してください。';
$string['norecording'] = '録音は提出されていません。';
$string['norecordingpermission'] = 'このブラウザまたはデバイスはマイクにアクセスできませんでした。許可を確認して、もう一度お試しください。';
$string['notimagefile'] = 'アップロードされたファイルの中に、画像でもPDFでもないものがあります。それを削除して、別のファイルをアップロードしてください。';
$string['notmediafile'] = 'アップロードされたファイルは、音声ファイルでも動画ファイルでもありません。別のファイルをアップロードしてください。';
$string['nowebrtc'] = 'お使いのブラウザは、WebRTC技術にまだ十分に対応していないか、まったく対応していないため、このタイプの問題には使用できません。ブラウザを変更するか、アップグレードしてください。';
$string['nowebrtctitle'] = 'WebRTCに対応していません';
$string['pageremoved'] = 'ページを削除しました。';
$string['pagerotated'] = 'ページを回転しました。';
$string['pagex'] = '{$a}ページ';
$string['pause'] = '一時停止';
$string['phrasecasesensitive0'] = '大文字と小文字を区別しない。';
$string['phrasecasesensitive1'] = '大文字と小文字を区別する。';
$string['phrasefullmatch0'] = '語の全体または一部に一致する。';
$string['phrasefullmatch1'] = '語の全体にのみ一致する。';
$string['phrasegroup'] = '目標フレーズ [{no}]';
$string['phrasegroup_help'] = 'このフレーズが解答で使われた場合に与えられる、問題評点の割合です。使われたすべての目標フレーズの割合は、目標項目とAI評価 (使用する場合) と合わせて合計され、合計の上限は100%です。「…で割った値」を使うと、整数でない割合を作れます。例えば、100%を3で割った値は33.33%です。

フレーズは文字起こしのどこにあっても一致し、フレーズ内のスペースは文字起こし内の任意のスペースに一致します。

> **例** もし [I have played] が使われた場合、問題評点の [10%] を与えます。

フレーズは、「小論文 (自動評定)」と同じように、次の語と記号を使って、平易な英語で書くことができます:

* **OR** またはカンマは、選択肢を区切ります。

> **例** もし [Finally OR In the end] が使われた場合、問題評点の [5%] を与えます。

* **AND** は、その後に続くものが解答の後の方に現れることを意味します。**ANY** は AND と同じ意味です。

> **例** もし [First AND Finally] が使われた場合、問題評点の [5%] を与えます。

* **括弧** は、フレーズの部分をまとめます。

> **例** もし [First AND (Then OR Next) AND (Finally OR In the end)] が使われた場合、問題評点の [10%] を与えます。

* 語の中の **\*** は任意の数の文字 (0文字を含む) に、**?** は1文字 (または0文字) に一致します。\* 単独では、任意の1語に一致します。

> **例** もし [walk\*] が使われた場合、問題評点の [5%] を与えます。これは "walk"、"walks"、"walked"、"walking" に一致します。

OR、AND、ANY は大文字で書く必要があります。小文字の場合は、普通の語として扱われます。AND は OR より強く結び付くため、"Hello OR Good AND bye" は、"Hello"、または "Good" の後に "bye" が続くもの、を意味します。カンマは OR を意味するため、フレーズにカンマそのものを含めることはできません。文字起こしには句読点が確実に含まれるわけではないので、これが問題になることはほとんどありません。

ラベルがない限り、学生にはフィードバックとヒントの中でフレーズそのものが表示されます。

より細かく制御するには、フレーズを正規表現にすることができます。"/" で始め、"/" と任意のフラグで終わります。

> **例** もし [/\\bgood (morning|afternoon)\\b/i] が使われた場合、問題評点の [5%] を与えます。';
$string['phraselabel'] = '目標フレーズのラベル';
$string['phraselabel_help'] = '目標フレーズの代わりに学生に表示されます。例:「関係詞節 (who, which, whose, where)」。フレーズが学生には分からない正規表現である場合や、平易な英語で書いたフレーズでは答えが分かってしまう場合に便利です。ラベルがない場合、学生にはフレーズそのもの、正規表現の場合は「目標フレーズ 1」、「目標フレーズ 2」などが表示されます。教師には常にフレーズが表示されます。';
$string['phraselabelno'] = '目標フレーズ [{no}] のラベル';
$string['phraselabelplaceholder'] = '学生向けのラベル (任意)';
$string['phrasenotused'] = '未使用';
$string['phrasenumber'] = '目標フレーズ {$a}';
$string['phraseoptions'] = '目標フレーズ [{no}] のオプション';
$string['phrasesheader'] = '目標フレーズ';
$string['phrasesheader_help'] = '解答で使われた目標フレーズは、それぞれ問題評点の割合を与えます。割合は合計され、上限は100%です。フレーズは解答の文字起こしで確認されます。録音の場合はブラウザのライブ文字起こし、またはAIプロバイダが作成した文字起こしです。

フレーズは、1つの語や句にすることも、OR、AND、括弧を使うこともできます。例: "First AND (Then OR Next) AND (Finally OR In the end)"。詳しくは、各目標フレーズのヘルプを参照してください。';
$string['phrasesmatched'] = '使われた目標フレーズ: {$a->total}件中{$a->matched}件 (問題評点の{$a->percent}%)。';
$string['phrasetext1'] = 'もし';
$string['phrasetext2'] = 'が使われた場合、問題評点の';
$string['phrasetext3'] = 'を与えます。';
$string['phraseused'] = '使用: 「{$a}」';
$string['phraseusedplain'] = '使用';
$string['playprompt'] = 'プロンプトを再生する';
$string['playpromptagain'] = 'プロンプトをもう一度再生する';
$string['pluginname'] = 'SpeakUp - 口頭解答';
$string['pluginname_help'] = '学生は、文字または音声のプロンプトに対して、制限時間内に口頭の解答を録音します。解答は、模範解答/パターンに照らして自動的に評定するか、AI評価エンジンで評定するか、教師が手動で確認することができます。';
$string['pluginname_link'] = 'question/type/speakup';
$string['pluginnameadding'] = 'SpeakUp口頭解答問題を追加する';
$string['pluginnameediting'] = 'SpeakUp口頭解答問題を編集する';
$string['pluginnamesummary'] = '学生は時間制限のある口頭の解答を提出し、自動 (目標フレーズとAI) または教師によって評定されます。';
$string['preset'] = 'プリセットから始める';
$string['preset_help'] = 'プリセットとは、新しい問題のために保存された、設定、目標項目、目標フレーズのセットです。例えば、短い口頭の答えや、動画でのスピーチのためのものがあります。プリセットを選択すると、その設定がフォームに入力されます。その後、問題を保存する前に、どの設定でも変更できます。

フォームの他の部分を入力する前に、プリセットを選択してください。フォームにすでに入力した内容は消去されます。';
$string['presetcontextid'] = '使用できるユーザ';
$string['presetcontextid_help'] = 'このプリセットから新しい問題を始められるユーザです。自分のみ、このコースの教師、コースカテゴリで問題を作成するすべてのユーザ、またはサイトの全員です。プリセットを共有するには、そこで「qtype/speakup:sharepresets」ケイパビリティが必要です。';
$string['presetdelete'] = 'プリセット「{$a}」を削除する';
$string['presetdeleteconfirm'] = 'プリセット「{$a}」を削除しますか? このプリセットから始めた問題は変更されません。';
$string['presetdeleted'] = 'プリセット「{$a}」を削除しました。';
$string['presetdescription'] = 'プリセットの説明';
$string['presetedit'] = 'プリセット「{$a}」を編集する';
$string['presethandwritingsample'] = '手書きサンプル';
$string['presethandwritingsample_desc'] = '手書きの答案の写真またはスキャンです。画像を読み取れるAIプロバイダが文字起こしと評価を行います。';
$string['presetinstantresponse'] = '即答スピーキング';
$string['presetinstantresponse_desc'] = 'プロンプトを1回再生するとすぐに録音される、短い口頭の答えです。例: 会話の中での素早い返答。';
$string['presetname'] = 'プリセットの名称';
$string['presetnone'] = 'なし (通常の設定)';
$string['presetowner'] = '所有者';
$string['presetreplace'] = '同じ省略名のプリセットを置き換える';
$string['presetreplace_help'] = 'チェックすると、同じユーザ向けにこの省略名のプリセットがすでにある場合、この問題の設定で置き換えられます。プリセットの設定を変更するには、この方法を使います。プリセットから新しい問題を始め、設定を変更し、同じ省略名で再びプリセットとして保存します。';
$string['presets'] = 'SpeakUpプリセット';
$string['presetsave'] = 'プリセットを保存する';
$string['presetsavecategory'] = 'カテゴリの教師: {$a}';
$string['presetsavecourse'] = 'コースの教師: {$a}';
$string['presetsaved'] = 'プリセット「{$a}」を保存しました。';
$string['presetsaveheader'] = 'プリセットとして保存する';
$string['presetsavesystem'] = 'このサイトの全員';
$string['presetsaveuser'] = '自分のみ';
$string['presetsettingsnote'] = 'プリセットの設定を変更するには、プリセットから新しい問題を始め、設定を変更し、「同じ省略名のプリセットを置き換える」をチェックして、同じ省略名で再びプリセットとして保存してください。';
$string['presetsheading_desc'] = 'プリセットとは、新しいSpeakUp問題の出発点として使える、設定、目標項目、目標フレーズです。教師は問題フォームからプリセットを保存します。自分用、コースやカテゴリ用、または (サイトで「qtype/speakup:sharepresets」ケイパビリティを持っている場合は) サイトの全員用に保存できます。';
$string['presetshortname'] = 'プリセットの省略名';
$string['presetshortname_help'] = 'プリセットの短い名前です。例: instant-response。GIFTまたはMoodle XMLファイルから問題をインポートするとき、この名前でプリセットを指定します。';
$string['presetsintro'] = 'ここで新しいSpeakUp問題の出発点として使えるプリセットです。自分で保存できるプリセットについては、名称、省略名、説明、共有範囲を変更できます。プリセットを追加したり、その設定を変更したりするには、問題をプリセットとして保存してください。';
$string['presetvideomonologue'] = '動画モノローグ';
$string['presetvideomonologue_desc'] = '自己紹介や短いプレゼンテーションなど、動画での少し長めのスピーチです。AIがその音声から評価します。';
$string['presetwithowner'] = '{$a->name} ({$a->owner})';
$string['privacy:metadata:ai_provider'] = 'AI評価を使用する問題では、学生の録音・録画とその文字起こしの一方または両方が、文字起こし、評価、フィードバックのために、AIブリッジを通してAIサービス (Moodle自体のAIサブシステム、またはサイトや教師が設定したOpenAIやGoogle Geminiなどのプロバイダ) に送信されます。教師が書いた音声プロンプトの文字起こしは、音声を生成するために音声プロバイダに送信されます。これには学生に関するデータは含まれません。';
$string['privacy:metadata:ai_provider:recording'] = '学生の音声/動画の録音・録画、または筆記した答案の画像です。それらを直接処理できるプロバイダの場合に、AIプロバイダに送信されます。';
$string['privacy:metadata:ai_provider:transcript'] = '学生の口頭の解答の文字起こしです。評価のためにAIプロバイダに送信されます。';
$string['privacy:metadata:audiofile'] = 'Moodleファイルとして保存された、学生の声を含む録音そのものです。';
$string['privacy:metadata:externalai'] = 'AIによる総合評価を使用する場合、学生の解答の文字起こし (および場合によっては音声) が、評価のために外部のAI/文字起こしサービスに送信されます。';
$string['privacy:metadata:externalai:audio'] = '設定されたサービスが自身で文字起こしを行う場合の、録音です。';
$string['privacy:metadata:externalai:transcript'] = '口頭の解答の文字起こしです。';
$string['privacy:metadata:qtype_speakup_presets'] = 'プリセット: 新しいSpeakUp問題の出発点として使える設定です。教師が自分用に、または他のユーザと共有するために保存します。';
$string['privacy:metadata:qtype_speakup_presets:description'] = 'プリセットの説明です。';
$string['privacy:metadata:qtype_speakup_presets:name'] = 'プリセットの名称です。';
$string['privacy:metadata:qtype_speakup_presets:settings'] = '目標項目と目標フレーズを含む、プリセットの設定です。';
$string['privacy:metadata:qtype_speakup_presets:shortname'] = '問題のインポート時に使用される、プリセットの省略名です。';
$string['privacy:metadata:qtype_speakup_presets:timecreated'] = 'プリセットが最初に保存された日時です。';
$string['privacy:metadata:qtype_speakup_presets:timemodified'] = 'プリセットが最後に変更された日時です。';
$string['privacy:metadata:qtype_speakup_presets:userid'] = 'プリセットを所有する教師です。その名前のイニシャルがプリセットの名称と一緒に表示されます。';
$string['privacy:metadata:qtype_speakup_response'] = '口頭の解答に関する受験ごとの情報です。文字起こしと、AIによるその評価です。';
$string['privacy:metadata:qtype_speakup_response:aifeedback'] = 'この解答についてAIプロバイダが生成したフィードバックです。';
$string['privacy:metadata:qtype_speakup_response:aiscore'] = 'この解答についてAIプロバイダが生成したスコア (0-1) です。';
$string['privacy:metadata:qtype_speakup_response:questionattemptid'] = 'この解答が属する問題の受験です。';
$string['privacy:metadata:qtype_speakup_response:timecreated'] = 'このレコードが作成された日時です。';
$string['privacy:metadata:qtype_speakup_response:timemodified'] = 'このレコードが最後に更新された日時です。';
$string['privacy:metadata:qtype_speakup_response:transcript'] = '学生の口頭の解答のテキストによる文字起こしです。';
$string['privacy:metadata:speakup_response'] = 'SpeakUp問題に対する学生の口頭の解答に関する情報です。';
$string['privacy:metadata:speakup_response:aifeedback'] = 'AI評価エンジンが生成したフィードバックです。';
$string['privacy:metadata:speakup_response:questionattemptid'] = 'この解答が属する問題の受験です。';
$string['privacy:metadata:speakup_response:transcript'] = '学生の録音から生成された自動の文字起こしです。';
$string['promptheader'] = 'プロンプト';
$string['promptpause'] = 'プロンプトの一時停止を許可する';
$string['promptpause_help'] = 'チェックすると、学生はプロンプト (上記の音声プロンプト、ない場合は問題テキストの最初の音声プレーヤー) を一時停止したり、前後に移動したりできます。チェックしない場合、プロンプトには通常のプレーヤーの代わりに「再生」ボタンが表示されます。一度始まると最後まで再生されるため、学生はプロンプトを止めて答えを準備する時間を増やすことができません。';
$string['promptplaying'] = 'プロンプトを再生中... {$a}';
$string['promptplaysused'] = 'プロンプトを許可された回数だけ再生しました。';
$string['prompttranscript'] = '音声プロンプトの文字起こし';
$string['prompttranscript_help'] = '音声プロンプトの文字起こしです。例えば、プロンプトを聞くことができない学生のためのものです。プロンプトを録音している間、ブラウザの音声認識 (利用可能な場合) がここに文字起こしを書き込みます。内容を確認し、誤りがあれば訂正してください。

サイトに音声プロバイダがあり、問題の保存時に音声プロンプトがない場合は、この文字起こしから音声プロンプトが生成されます。';
$string['prompttranscriptcollapsed'] = 'はい、折りたたんで表示する';
$string['prompttranscriptdisplay'] = '音声プロンプトの文字起こしを表示する';
$string['prompttranscriptdisplay_help'] = '音声プロンプトの文字起こしを学生にどのように表示するか:

* **いいえ** - 文字起こしは表示されません。
* **はい、折りたたんで表示する** - 学生には「文字起こしを表示する」リンクが表示され、表示するかどうかを選択できます。
* **はい、展開して表示する** - 文字起こしは、常に音声プロンプトの下に表示されます。';
$string['prompttranscriptexpanded'] = 'はい、展開して表示する';
$string['prompttranscriptpenalty'] = '文字起こし表示のペナルティ';
$string['prompttranscriptpenalty_help'] = '学生が音声プロンプトの折りたたまれた文字起こしを表示した場合に差し引かれる、問題評点の割合です。

プロンプトを聞くことができないなどの理由で文字起こしを必要とする学生は、ペナルティを免除できます。例えば、コースでそれらの学生用のロールを使って、「qtype/speakup:viewtranscriptwithoutpenalty」ケイパビリティを付与してください。教師は、学生が文字起こしを表示したかどうかを確認できます。

ペナルティのある問題は、解答の送信後、バックグラウンドで評定されます。';
$string['promptvoice'] = '音声';
$string['promptvoice_help'] = '文字起こしから音声プロンプトを生成するときに使用する音声です。例: OpenAIの場合は「alloy」、Geminiの場合は「Kore」。空欄にすると、問題の言語に対するサイトの音声、それもない場合はサイトのデフォルトの音声が使用されます。';
$string['questionidnumber'] = '問題のIDナンバー';
$string['recordagain'] = '録り直す';
$string['recordagainx'] = '{$a}を録り直す';
$string['recordaudioprompt'] = '音声プロンプトを録音する';
$string['recordingfailed'] = '録音に失敗しました';
$string['recordingsubmitted'] = '録音を提出しました';
$string['recordyouranswer'] = '答えを録音してください';
$string['reducefiles'] = '大きな解答ファイルを小さくする';
$string['reducefiles_desc'] = '学生の大きな音声、動画、画像、PDFファイルを、小テストの送信後にバックグラウンドで小さくします。音声はモノラルのMP3に、動画は720pのMP4に、画像は幅・高さとも最大2000ピクセルのJPEGに、PDFは画像を150 dpiにします。ファイルは、結果が明らかに小さくなる場合にのみ置き換えられます。必要なツールがない (上記を参照) ファイルは、そのまま残ります。';
$string['reducefilesheading'] = '大きな解答ファイル';
$string['reducefilesheading_desc'] = '学生は、長い動画やスマートフォンで撮ったままの写真など、非常に大きなファイルをアップロードすることがよくあります。ファイルを小さくすると、容量が節約でき、教師がすばやく開けるようになり、AIプロバイダに拒否される可能性も低くなります。これには、サーバ上の次のプログラムを使用します:';
$string['reducesettings'] = 'ffmpegの場所と、その他のメディアの変換方法は、<a href="{$a}">AIブリッジの設定</a> (メディアファイル) で設定します。';
$string['reducethreshold'] = '次より大きいファイルを小さくする';
$string['reducethreshold_desc'] = 'これより小さいファイルは、そのまま残ります。';
$string['removepage'] = '削除';
$string['removepagex'] = '{$a}ページを削除する';
$string['replaceoriginals'] = '元のファイルを置き換える';
$string['replaceoriginals_desc'] = 'チェックすると、大きなファイルは小さいファイルで置き換えられ、容量が節約できます。チェックしない場合、元のファイルは残り、小さいファイルは解答を評価するときにAIプロバイダ用にのみ作成されます。';
$string['responseheader'] = '解答';
$string['responsemethod'] = '解答方法';
$string['responsemethod_help'] = '学生がどのように解答を提出するか:

* **録音** - 学生はブラウザで、その場で解答を録音します。
* **アップロード** - 学生は音声または動画ファイルをアップロードします。スマートフォンでは、ファイルを選択するときに、通常はその場で録音することもできます。

アップロードされたファイルにはライブ文字起こしがありません。そのため、手動で評定する場合を除き、まず音声または動画ファイルそのものを受け取るAIプロバイダが文字起こしを行い、次に目標フレーズとAIの一方または両方で評定されます。これは解答の送信後、バックグラウンドで行われます。制限時間はアップロードにも適用されます。制限時間より長いファイルは、解答を確認するときに、そのことが示されます。';
$string['responsemethod_record'] = '録音';
$string['responsemethod_upload'] = 'アップロード';
$string['responsepause'] = '一時停止を許可する';
$string['responsepause_help'] = '学生が録音の途中で一時停止と再開をできるかどうかです。デフォルトではオフです。録音途中の一時停止/再開は、処理が不安定になりやすく (ブラウザのライブ文字起こしを使用している場合は、それも再起動されます)、学生が途中で本当に一時停止する必要がない限り、オフのままにしてください。';
$string['responsesused'] = '許可された回数だけ解答しました。';
$string['resume'] = '再開';
$string['rotateleft'] = '左に回転';
$string['rotateleftx'] = '{$a}ページを左に回転する';
$string['rotateright'] = '右に回転';
$string['rotaterightx'] = '{$a}ページを右に回転する';
$string['rubricitemchars'] = '{$a}文字';
$string['rubricitemcharspersentence'] = '1文あたり{$a}文字';
$string['rubricitemlexicaldensity'] = '語彙密度{$a}%';
$string['rubricitemlongwords'] = '長い語{$a}語';
$string['rubricitemlongwordspersentence'] = '1文あたり長い語{$a}語';
$string['rubricitemsentences'] = '{$a}文';
$string['rubricitemspeechrate'] = '1分あたり{$a}語';
$string['rubricitemuniquewords'] = '異なり語数{$a}語';
$string['rubricitemwords'] = '{$a}語';
$string['rubricitemwordspersentence'] = '1文あたり{$a}語';
$string['rubricplays1'] = '1回';
$string['rubricplays2'] = '2回';
$string['rubricplaysany'] = '好きなだけ';
$string['rubricplaysn'] = '{$a}回';
$string['rubricresponses1'] = '解答1回';
$string['rubricresponses2'] = '解答2回';
$string['rubricresponsesany'] = '解答回数無制限';
$string['rubricresponsesn'] = '解答{$a}回';
$string['rubrictimeminute'] = '1分';
$string['rubrictimeminutes'] = '{$a}分';
$string['rubrictimenolimit'] = '時間制限なし';
$string['rubrictimesecond'] = '1秒';
$string['rubrictimeseconds'] = '{$a}秒';
$string['savepreset'] = 'これらの設定をプリセットとして保存する';
$string['savepreset_help'] = 'チェックすると、この問題の設定が、目標項目と目標フレーズとともに、プリセットとしても保存されます。新しい問題はこのプリセットから始めることができます。問題名、問題テキスト、音声プロンプトはプリセットに保存されません。';
$string['showcalculation'] = '評点の計算を表示する';
$string['showcalculation_help'] = '評点の計算方法を表示します。最高評点、各目標項目、目標フレーズ、総合 (AI) 評価によって得た割合、およびその結果の評点です。';
$string['showcomment'] = 'AIのフィードバックを表示する';
$string['showcomment_help'] = 'AI評価のフィードバックを表示します。フィードバックは評定コメントとして保存されるため、この設定は教師が追加したコメントにも適用されます。';
$string['showheader'] = '評定の詳細';
$string['showheader_help'] = '解答を確認するときに、自動評点の各詳細を誰に表示するかを選択します。小テストを評定できる閲覧者は教師として、それ以外の全員は学生として扱われます。';
$string['showitems'] = '目標項目を表示する';
$string['showitems_help'] = '解答の各目標項目 (または評点帯) を表示します。例えば語数について、解答がどれだけ満たしたか、問題評点のどれだけの割合を得たかです。学生には、達成しなかった目標についてのヒントも表示されます。';
$string['showphrases'] = '目標フレーズを表示する';
$string['showphrases_help'] = '解答でどの目標フレーズが使われたか、一致した語、それぞれが得た問題評点の割合を表示します。学生には、ラベルがある場合は各フレーズのラベルが表示されます。学生には、使わなかったフレーズについてのヒントも表示されます。';
$string['showstats'] = '統計を表示する';
$string['showstats_help'] = '解答を確認するときに、解答の文字起こしに関する統計 (例えば語数) を表示します。表示する統計は下で選択してください。';
$string['showtostudentsonly'] = 'はい、学生のみに表示する';
$string['showtoteachersandstudents'] = 'はい、教師と学生に表示する';
$string['showtoteachersonly'] = 'はい、教師のみに表示する';
$string['showtranscript'] = '文字起こしを表示する';
$string['showtranscript_help'] = '解答の文字起こしを表示します。AIプロバイダが作成した文字起こしがある場合はそれ、ない場合はブラウザの音声認識によるライブ文字起こしです。';
$string['showtranscriptpenalty'] = '(ペナルティ -{$a}%)';
$string['silencestop'] = '次の時間無音が続いたら録音を停止する';
$string['silencestop_help'] = '学生がこの時間だけ無音でいると、録音は自動的に停止します。無音は学生が話し始めてから数えるため、考えている間に録音が切られることはありません。録音の一時停止 (許可されている場合) は無音として数えません。';
$string['silencestopped'] = '{$a}秒間無音が続いたため、録音を停止しました。';
$string['speakup:downloadrecordings'] = '録音をダウンロードする';
$string['speakup:sharepresets'] = 'SpeakUp問題のプリセットを共有する';
$string['speakup:viewtranscriptwithoutpenalty'] = 'ペナルティなしで音声プロンプトの文字起こしを表示する';
$string['startcamerax'] = 'カメラ{$a}を開始する';
$string['startrecording'] = '録音を開始する';
$string['startrecordingx'] = '{$a}の録音を開始する';
$string['startsharescreen'] = '画面録画を開始する';
$string['statistics'] = '統計';
$string['statsaigroup'] = 'AIの文字起こしがある場合';
$string['statsaigroup_help'] = 'これらの統計には文が必要で、文は句読点のある文字起こしでのみ数えることができます。句読点があるのはAIプロバイダが作成した文字起こしのみであるため、これらの統計はそれがある場合にのみ表示されます。

フォグ指数は、言葉がどれだけ分かりにくいかを推定するもので、0.4 x (1文あたりの語数 + 100 x 長い語 / 語数) です。';
$string['statsgroup'] = '統計';
$string['statsgroup_help'] = '表示する統計です。解答の文字起こしで数えます。';
$string['stoprecording'] = '録音を停止する';
$string['sttlanguage'] = '解答の言語';
$string['sttlanguage_help'] = '学生が話すことになっている言語を、言語タグで指定します。例: "en-US"、"en-GB"、"ja-JP"。

その場での録音の場合、ブラウザ組み込みの音声認識 (利用可能な場合) が、これを使って即時の文字起こしを作成します。学生のブラウザが音声認識やこの言語に対応していない場合、ライブ文字起こしは省略されますが、解答は通常どおり録音されます。

アップロードされたファイルの場合、文字起こしの際のヒントとしてAIプロバイダに渡されます。';
$string['subtotal'] = '最大{$a}%';
$string['suggestedmatch_none'] = 'この解答には文字起こしがないため、目標フレーズを確認できませんでした。';
$string['taskassessresponse'] = 'SpeakUpの解答を評価する';
$string['taskgenerateaudioprompt'] = '文字起こしから音声プロンプトを生成する';
$string['taskreduceresponsefiles'] = '大きなSpeakUpの解答ファイルを小さくする';
$string['timedisplay'] = '{$a->mins}:{$a->secs}';
$string['timelimitinseconds'] = '解答の制限時間';
$string['timelimitinseconds_help'] = '学生が録音を開始してから、口頭の解答を録音できる最大時間 (秒) です。';
$string['timeremaining'] = '残り時間: {$a}';
$string['toomanyfiles'] = '最大{$a}個のファイルをアップロードできます。';
$string['transcript'] = '文字起こし';
$string['transcriptionproviderid'] = '文字起こしプロバイダ';
$string['transcriptionproviderid_help'] = 'アップロードされたファイルにはライブ文字起こしがないため、まずAIプロバイダが文字起こしを行い、その文字起こしが目標フレーズと目標項目で評定されます。

* AI評価を使用しない場合は、ここでAIプロバイダを選択してください。音声または動画ファイルそのものを受け取るプロバイダである必要があります。「自動」を選択すると、AIブリッジが選択します。
* AI評価を使用する場合は、AI評価設定のAIプロバイダが、ファイルの評価に加えて文字起こしも行います。';
$string['transcriptionprovidernote'] = 'アップロードされたファイルは、下記の<a href="#id_aisettings">AI評価設定</a>の「AIプロバイダ」によって文字起こしされます。';
$string['transcriptviewed'] = '文字起こしを表示しました。';
$string['transcriptviewedexempt'] = '文字起こしを表示しました (免除: ペナルティなし)。';
$string['ttsheading'] = 'テキスト読み上げ';
$string['ttsheading_desc'] = '音声プロバイダは、文字起こしから音声プロンプトを生成できます。例えば、GIFTからインポートした問題や、音声プロンプトなしで文字起こしだけを付けて保存した問題のためです。音声はバックグラウンドで、通常は数分以内に生成されます。音声プロバイダは、AIブリッジで「Speech」の機能を付けて設定します。';
$string['ttsproviderid'] = '音声プロバイダ';
$string['ttsproviderid_desc'] = '文字起こしから音声プロンプトを生成するプロバイダです。「任意の音声プロバイダ」を選択すると、問題がある場所で教師が使用できる音声プロバイダが使用されます。この機能をオフにするには「なし」を選択してください。';
$string['ttsprovidersany'] = '任意の音声プロバイダ';
$string['ttsvoice'] = 'デフォルトの音声';
$string['ttsvoice_desc'] = '問題にも、下記の言語ごとの音声にも指定がない場合に、音声プロンプトに使用する音声です。例: OpenAIの場合は「alloy」、Geminiの場合は「Kore」。空欄にすると、プロバイダ自身のデフォルトが使用されます。';
$string['ttsvoices'] = '言語ごとの音声';
$string['ttsvoices_desc'] = '1行に1つ、言語コード、「=」、音声を書きます。例: "en-GB=fable"、"ja=Kore"。"en" の行は、専用の行がない英語 (例: "en-AU") すべてに使用されます。';
$string['uploadaborted'] = '保存を中止しました';
$string['uploadanotherimage'] = '別のページをアップロードする';
$string['uploadcomplete'] = '録音をアップロードしました';
$string['uploaddifferentfile'] = '別のファイルをアップロードする';
$string['uploadfailed'] = 'アップロードに失敗しました';
$string['uploadfailed404'] = 'アップロードに失敗しました (ファイルが大きすぎる可能性があります)';
$string['uploadfile'] = 'ファイルをアップロードする';
$string['uploadimage'] = '画像またはPDFをアップロードする';
$string['uploadpreparing'] = 'アップロードを準備中 ...';
$string['uploadpreparingpercent'] = 'アップロードを準備中 ({$a}%)';
$string['uploadprogress'] = 'アップロード中 ({$a})';
$string['uploadtoobig'] = 'このファイルは大きすぎます。アップロードできる最大のファイルサイズは{$a}です。';
$string['uploadtoobigmedia'] = 'このファイルは{$a->size}ありますが、アップロードできる最大のファイルサイズは{$a->limit}です。もっと短い録音をアップロードするか、例えば低い品質で録音して、ファイルを小さくしてください。';
$string['uploadtoobigpdf'] = '「{$a->name}」は{$a->size}ありますが、アップロードできる最大のファイルサイズは{$a->limit}です。例えば、低い解像度や白黒でスキャンして、ファイルを小さくしてください。';
$string['uploadtoolong'] = 'このファイルの長さは{$a->duration}秒ですが、制限時間は{$a->limit}秒です。アップロードはされましたが、長すぎると表示されます。';
$string['uploadwait'] = 'あなたの答えを保存しています。お待ちください。保存され次第、次に進みます。';
$string['uploadwrongtype'] = '音声または動画ファイルを選択してください。';
$string['uploadwrongtypeaudio'] = '音声ファイルを選択してください。';
$string['uploadwrongtypeimage'] = '画像またはPDFファイルを選択してください。';
$string['usepreset'] = 'このプリセットを使用する';
