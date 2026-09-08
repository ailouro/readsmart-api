import glob

files = glob.glob('database/migrations/*_add_test_type_to_class_story_table.php')
if files:
    filename = files[0]
    content = open(filename).read()
    new_content = content.replace(
        "Schema::table('class_story', function (Blueprint $table) {",
        "Schema::table('class_story', function (Blueprint $table) {\n            $table->string('test_type')->default('post_test');"
    )
    new_content = new_content.replace(
        "public function down(): void\n    {\n        Schema::table('class_story', function (Blueprint $table) {",
        "public function down(): void\n    {\n        Schema::table('class_story', function (Blueprint $table) {\n            $table->dropColumn('test_type');"
    )
    open(filename, 'w').write(new_content)
    print('Updated migration file')
