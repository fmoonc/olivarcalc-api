<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\AffiliateProduct;
use App\Models\Article;
use App\Models\Category;
use App\Models\ToolPage;
use App\Models\User;
use Illuminate\Database\Seeder;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::where('email', 'admin@olivarcalc.test')->first()
            ?? User::factory()->create([
                'name' => 'Administrador',
                'email' => 'admin@olivarcalc.test',
                'role' => UserRole::Admin,
            ]);
        // Articles and tool pages reference this author by id.

        $cultivo = Category::updateOrCreate(
            ['slug' => 'cultivo'],
            ['name' => 'Cultivo', 'description' => 'Poda, riego, abonado y sanidad del olivar.', 'sort_order' => 1],
        );
        $cosecha = Category::updateOrCreate(
            ['slug' => 'cosecha-y-almazara'],
            ['name' => 'Cosecha y almazara', 'description' => 'Recogida, Indices de calidad y liquidaciones.', 'sort_order' => 2],
        );
        $precios = Category::updateOrCreate(
            ['slug' => 'precios'],
            ['name' => 'Precios', 'description' => 'Precios del aceite en origen y su evolución.', 'sort_order' => 3],
        );

        $articles = [
            [
                'slug' => 'indice-corrector-como-se-usa',
                'title' => 'Qué es el índice corrector y cómo afecta a tu liquidación',
                'excerpt' => 'El aceite que no se extrae y queda en el alpeorujo no se paga. Explicamos el índice corrector paso a paso.',
                'category' => $cosecha,
                'related_tool' => 'settlement',
                'body' => "# Qué es el índice corrector\n\nEl rendimiento graso total que da el laboratorio no es lo que se paga. Del aceite extraído queda una parte en el **alpeorujo**, y esa parte no se factura. Esa diferencia, en puntos porcentuales, es el **índice corrector**.\n\n## Cómo se aplica\n\nSe resta al rendimiento graso total:\n\n```\nrendimiento industrial = RGT − índice corrector\n```\n\nUn índice alto para un RGT bajo es una señal de que el aceituno está muy húmedo o sucio.\n\n## Fuentes\n\n- Reglamento del IRPF, art. 95.4\n- Convenios colectivos del campo de cada provincia",
            ],
            [
                'slug' => 'rendimiento-graso-total-rgt',
                'title' => 'Rendimiento graso total: qué mide y por qué no te pagan esa cifra',
                'excerpt' => 'El análisis de laboratorio da el RGT sobre aceituna entera, con humedad. Lo que se liquida es el rendimiento industrial.',
                'category' => $cultivo,
                'related_tool' => 'yield_converter',
                'body' => "# Rendimiento graso total\n\nEl **RGT** es el porcentaje de aceite sobre el peso total de la aceituna, tal y como llega al laboratorio, con su humedad.\n\n## Por qué no coincide con lo que cobras\n\n- El RGT incluye el aceite que se queda en el alpeorujo.\n- No distingue aceituna de vuelo de aceituna de suelo.\n\nLo que se liquida es el **rendimiento industrial**: RGT menos índice corrector.",
            ],
            [
                'slug' => 'precios-aceite-origen',
                'title' => 'Cómo se forman los precios del aceite en origen',
                'excerpt' => 'Qué es la campaña oleícola, por qué empieza el 1 de octubre y qué calidad se paga más.',
                'category' => $precios,
                'related_tool' => null,
                'body' => "# Precios en origen\n\nLa **campaña** oleícola va del 1 de octubre al 30 de septiembre. Dentro de una campaña se cotizan tres calidades: extra, virgen y lampante.\n\n## A qué se Ira\n\nEl precio se pacta por tonelada de aceite en origen, no por kilo de aceituna. Si te pagan por aceituna, el cálculo es otro.\n\n## Ojo\n\nLos precios que ves en esta web son orientativos y se marcan como provisionales hasta confirmarlos.",
            ],
        ];

        foreach ($articles as $data) {
            Article::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'title' => $data['title'],
                    'excerpt' => $data['excerpt'],
                    'body_markdown' => $data['body'],
                    'category_id' => $data['category']->id,
                    'related_tool' => $data['related_tool'],
                    'meta_title' => mb_substr($data['title'], 0, 60),
                    'meta_description' => mb_substr($data['excerpt'], 0, 155),
                    'faq' => [
                        ['question' => '¿Necesito una cuenta para usar las calculadoras?', 'answer' => 'No. Todas las calculadoras funcionan sin cuenta; la cuenta solo sirve para guardar resultados.'],
                    ],
                    'status' => 'published',
                    'published_at' => now(),
                    'author_id' => $author->id,
                ],
            );
        }

        $tools = [
            ['settlement', 'Liquidación de aceituna', 'Calcula lo que te van a pagar por tu aceituna, con IVA o REAGP y retención de IRPF.'],
            ['yield_converter', 'Conversión de rendimiento', 'Pasa del rendimiento graso total al industrial, y de kg a litros.'],
            ['cost_per_hectare', 'Coste por hectárea', 'Coste y rentabilidad de tu olivar por hectárea.'],
            ['harvest_crew', 'Coste de cuadrilla de recogida', 'Calcula lo que cuesta recoger tu aceituna con una cuadrilla.'],
        ];

        foreach ($tools as [$key, $title, $intro]) {
            ToolPage::updateOrCreate(
                ['tool_key' => $key],
                [
                    'title' => $title,
                    'intro_markdown' => $intro,
                    'body_markdown' => "## Cómo se calcula\n\nLa fórmula vive en un paquete compartido y sus resultados se guardan tal cual los ves aquí.\n\n## Ejemplo\n\nIntroduce los datos de tu última entrega y compara el resultado con tu liquidación.\n\n## Fuentes oficiales\n\nCada parámetro usado lleva su fuente; los que aún no están verificados se avisan.",
                    'faq' => [
                        ['question' => '¿Es un resultado definitivo?', 'answer' => 'No. Es orientativo y no sustituye el asesoramiento profesional.'],
                    ],
                    'meta_title' => mb_substr($title, 0, 60),
                    'meta_description' => mb_substr($intro, 0, 155),
                ],
            );
        }

        $products = [
            ['Vareadora de olivar', 'vareadoras', 'https://www.amazon.es/s?k=vareadora+olivar', 'desde 1.200 €', 'Desgarra la aceituna sin moler la almendra.'],
            ['Malla para aceituna', 'mallas', 'https://www.amazon.es/s?k=malla+aceituna', 'desde 180 €', 'Malla vendimiadora de polietileno para recogida en suelo.'],
            ['Tijera de poda profesional', 'poda', 'https://www.amazon.es/s?k=tijera+poda+olivo', 'desde 45 €', 'Tijera profesional de una mano para olivar.'],
        ];

        foreach ($products as $i => [$name, $category, $url, $price, $description]) {
            AffiliateProduct::updateOrCreate(
                ['name' => $name],
                [
                    'category' => $category,
                    'url' => $url,
                    'price_text' => $price,
                    'short_description' => $description,
                    'active' => true,
                    'sort_order' => $i,
                ],
            );
        }
    }
}
