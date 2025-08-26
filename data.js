// Données du quiz Mission Cycle
const quizData = {
    title: "Mission Cycle",
    description: "Quiz de sensibilisation aux règles et au cycle menstruel",
    totalQuestions: 20,
    questions: [
        {
            id: 1,
            type: "quiz",
            question: "Considérez-vous les règles comme un sujet tabou ?",
            options: ["Oui", "Non"],
            correct: 0,
            time: 20,
            points: 500,
            category: "Tabous & perceptions",
            confirmation: {
                title: "Merci pour votre réponse !",
                text: "Une personne sur deux considère encore les règles comme un sujet tabou."
            },
            explanation: {
                title: "💡 Info éducative",
                text: "Le tabou autour des règles limite la discussion autour de la santé menstruelle et crée de l’anxiété.",
                media: null
            }
        },
        {
            id: 2,
            type: "quiz",
            question: "Au travail, à l’école ou en sport, qu’est-ce qui vous stresse le plus pendant vos règles ?",
            options: ["L’angoisse de tacher ses vêtements", "La peur de manquer de protections", "L’obsession de cacher tampons et serviettes"],
            correct: 0,
            time: 20,
            points: 500,
            category: "Stress & quotidien",
            confirmation: {
                title: "Merci pour votre réponse !",
                text: "Beaucoup partagent ce stress, il est important d’en parler."
            },
            explanation: {
                title: "💡 Info éducative",
                text: "Prévoir des protections de rechange et en parler librement peut réduire ce stress.",
                media: null
            }
        },
        {
            id: 3,
            type: "quiz",
            question: "Avez-vous déjà manqué l’école ou le travail à cause de vos règles ?",
            options: ["Oui", "Non"],
            correct: 0,
            time: 15,
            points: 500,
            category: "Impact social",
            confirmation: {
                title: "Réponse notée",
                text: "De nombreuses femmes et filles sont concernées par les absences liées aux règles."
            },
            explanation: {
                title: "💡 Info éducative",
                text: "44 % des femmes ont manqué le travail et 36 % des filles l’école pour cette raison.",
                media: null
            }
        },
        {
            id: 4,
            type: "quiz",
            question: "Combien de jours une femme est-elle menstruée en moyenne au cours de sa vie ?",
            options: ["1200", "2000", "2400"],
            correct: 2,
            time: 20,
            points: 1000,
            category: "Faits clés",
            confirmation: {
                title: "✅ Réponse : 2400 jours",
                text: "C’est effectivement une longue période de la vie !"
            },
            explanation: {
                title: "💡 Info éducative",
                text: "En moyenne, une femme a ses règles pendant environ 5 jours par mois entre 12 et 51 ans, soit 2400 jours au total.",
                media: null
            }
        },
        {
            id: 5,
            type: "quiz",
            question: "À combien revient l’achat de protections jetables au cours d’une vie ?",
            options: ["1800 €", "2800 €", "3800 €"],
            correct: 2,
            time: 20,
            points: 1000,
            category: "Faits clés",
            confirmation: {
                title: "✅ Réponse : ~3800 €",
                text: "Un budget considérable sur une vie entière."
            },
            explanation: {
                title: "💡 Info éducative",
                text: "En incluant d’autres dépenses (culottes, bouillotte, détachant, consultations), l’enveloppe globale peut approcher 5800 €.",
                media: null
            }
        },
        {
            id: 6,
            type: "quiz",
            question: "Qu’est-ce que la précarité menstruelle ?",
            options: ["Difficulté à se procurer des protections pour raisons financières", "Difficulté à se procurer des vêtements", "Difficulté à aller à l’école"],
            correct: 0,
            time: 20,
            points: 1000,
            category: "Faits clés",
            confirmation: {
                title: "✅ C’est exactement cela",
                text: "La précarité menstruelle touche environ une femme sur trois en France."
            },
            explanation: {
                title: "💡 Info éducative",
                text: "4 millions de femmes et personnes menstruées sont concernées en France.",
                media: null
            }
        },
        {
            id: 7,
            type: "true-false",
            question: "Une femme perd en moyenne l’équivalent d’1 à 3 cuillères à soupe de sang pendant ses règles.",
            options: ["Vrai", "Faux"],
            correct: 0,
            time: 15,
            points: 500,
            category: "Faits clés",
            confirmation: {
                title: "✅ Réponse : Vrai",
                text: "Le flux menstruel moyen est de 30 à 40 ml par cycle."
            },
            explanation: {
                title: "💡 Info éducative",
                text: "Le flux varie d’une personne à l’autre, mais il se situe généralement entre 30 et 40 ml.",
                media: null
            }
        },
        {
            id: 8,
            type: "quiz",
            question: "Combien de jours dure en moyenne un cycle menstruel ?",
            options: ["21 jours", "28 jours", "35 jours"],
            correct: 1,
            time: 15,
            points: 1000,
            category: "Connaissance du cycle",
            confirmation: {
                title: "✅ Réponse : 28 jours",
                text: "C’est la durée moyenne d’un cycle."
            },
            explanation: {
                title: "💡 Info éducative",
                text: "La durée moyenne est de 28 jours, mais elle peut varier de 21 à 35 jours selon les personnes.",
                media: 'assets/cycle_infographic.png'
            }
        },
        {
            id: 9,
            type: "quiz",
            question: "Les règles commencent :",
            options: ["Au milieu du cycle", "Au début du cycle", "À la fin du cycle"],
            correct: 1,
            time: 15,
            points: 1000,
            category: "Connaissance du cycle",
            confirmation: {
                title: "✅ Réponse : Au début du cycle",
                text: "Les règles marquent le premier jour du cycle menstruel."
            },
            explanation: {
                title: "💡 Info éducative",
                text: "Le premier jour des règles est considéré comme le premier jour du cycle.",
                media: null
            }
        },
        {
            id: 10,
            type: "quiz",
            question: "Combien y a-t-il de phases dans un cycle menstruel ?",
            options: ["2", "3", "4", "5"],
            correct: 2,
            time: 15,
            points: 1000,
            category: "Connaissance du cycle",
            confirmation: {
                title: "✅ Réponse : 4 phases",
                text: "Le cycle comporte 4 phases : menstruelle, pré‑ovulatoire, ovulatoire et pré‑menstruelle."
            },
            explanation: {
                title: "💡 Info éducative",
                text: "Chaque phase correspond à des variations hormonales qui influencent le corps et l’humeur.",
                media: null
            }
        },
        {
            id: 11,
            type: "quiz",
            question: "Que faites-vous pour rester à l’aise pendant vos règles ?",
            options: ["Préparer des protections de rechange", "Porter des vêtements confortables", "Avoir une bouillotte ou antidouleurs", "Boire beaucoup d’eau et se reposer"],
            correct: 0,
            time: 20,
            points: 500,
            category: "Bien-être",
            confirmation: {
                title: "Merci pour votre réponse !",
                text: "Chaque personne a ses propres astuces pour rester à l’aise."
            },
            explanation: {
                title: "💡 Info éducative",
                text: "Bien connaître son cycle et ses besoins permet de réduire le stress et améliorer le confort.",
                media: null
            }
        },
        {
            id: 12,
            type: "quiz",
            question: "À quelle fréquence doit-on changer tampons ou serviettes ?",
            options: ["Toutes les 2 à 8 heures", "Une fois par jour", "Quand elles sont pleines seulement"],
            correct: 0,
            time: 15,
            points: 1000,
            category: "Hygiène",
            confirmation: {
                title: "✅ Réponse : Toutes les 2 à 8 heures",
                text: "Il est important de changer régulièrement pour éviter les infections."
            },
            explanation: {
                title: "💡 Info éducative",
                text: "Changer régulièrement les protections évite les infections et les odeurs.",
                media: null
            }
        },
        {
            id: 13,
            type: "quiz",
            question: "Que faut-il utiliser pour se laver pendant les règles ?",
            options: ["Eau et savon doux ou nettoyant sans parfum", "Eau uniquement", "Savon parfumé fort ou gel douche"],
            correct: 0,
            time: 15,
            points: 1000,
            category: "Hygiène",
            confirmation: {
                title: "✅ Réponse : Savon doux ou nettoyant intime",
                text: "Cette option respecte le pH et évite les irritations."
            },
            explanation: {
                title: "💡 Info éducative",
                text: "Il est conseillé d’utiliser un savon doux ou un nettoyant intime sans parfum.",
                media: null
            }
        },
        {
            id: 14,
            type: "quiz",
            question: "Les douches vaginales sont :",
            options: ["Nécessaires pour être propre", "À éviter", "Recommandées pendant les règles"],
            correct: 1,
            time: 15,
            points: 1000,
            category: "Hygiène",
            confirmation: {
                title: "✅ Réponse : À éviter",
                text: "Les douches vaginales perturbent la flore et favorisent les infections."
            },
            explanation: {
                title: "💡 Info éducative",
                text: "Les douches vaginales ne sont pas recommandées car elles peuvent perturber la flore vaginale.",
                media: null
            }
        },
        {
            id: 15,
            type: "quiz",
            question: "Pour les protections lavables ou culottes menstruelles, que faut-il faire ?",
            options: ["Les rincer et laver selon les instructions", "Les réutiliser sans lavage", "Les laver seulement une fois par mois"],
            correct: 0,
            time: 15,
            points: 1000,
            category: "Hygiène",
            confirmation: {
                title: "✅ Réponse : Les laver correctement",
                text: "L’hygiène est essentielle pour la santé intime."
            },
            explanation: {
                title: "💡 Info éducative",
                text: "Les protections réutilisables doivent être lavées correctement pour rester hygiéniques.",
                media: null
            }
        },
        {
            id: 16,
            type: "quiz",
            question: "Quels signes peuvent indiquer une infection ? (cochez tout ce qui s’applique)",
            options: ["Démangeaisons ou brûlures", "Odeur désagréable", "Sécrétions inhabituelles", "Douleur intense", "Aucun"],
            correct: 0,
            time: 20,
            points: 1000,
            category: "Hygiène",
            confirmation: {
                title: "Merci pour votre réponse !",
                text: "Connaître les signes permet d’agir rapidement."
            },
            explanation: {
                title: "💡 Info éducative",
                text: "En présence de ces signes, il est important de consulter un professionnel de santé.",
                media: null
            }
        },
        {
            id: 17,
            type: "true-false",
            question: "Un peu de douleur pendant les règles est normal.",
            options: ["Vrai", "Faux"],
            correct: 0,
            time: 15,
            points: 500,
            category: "Santé",
            confirmation: {
                title: "✅ Réponse : Vrai",
                text: "Des crampes légères sont fréquentes, mais des douleurs très intenses ne sont pas normales."
            },
            explanation: {
                title: "💡 Info éducative",
                text: "Si la douleur est très intense et perturbe la vie quotidienne, consultez un médecin.",
                media: null
            }
        },
        {
            id: 18,
            type: "quiz",
            question: "Le SPM correspond à :",
            options: ["Une envie irrésistible de sucreries", "Un ensemble de symptômes physiques et émotionnels", "Un moment où les règles sont plus abondantes"],
            correct: 1,
            time: 15,
            points: 1000,
            category: "Santé",
            confirmation: {
                title: "✅ Réponse : Un ensemble de symptômes",
                text: "Le SPM peut inclure fatigue, irritabilité, ballonnements, seins sensibles…"
            },
            explanation: {
                title: "💡 Info éducative",
                text: "Le syndrome prémenstruel survient avant les règles et combine des symptômes physiques et émotionnels.",
                media: null
            }
        },
        {
            id: 19,
            type: "quiz",
            question: "Quand est-il conseillé de consulter un professionnel de santé ?",
            options: ["Règles très abondantes ou prolongées", "Douleurs intenses qui perturbent la vie quotidienne", "Cycles irréguliers ou absents", "Symptômes de fatigue, pâleur ou essoufflement", "SPM très handicapant"],
            correct: 0,
            time: 25,
            points: 1000,
            category: "Santé",
            confirmation: {
                title: "Merci pour votre réponse !",
                text: "Ces situations méritent un suivi médical."
            },
            explanation: {
                title: "💡 Info éducative",
                text: "Certaines douleurs ou anomalies peuvent signaler des troubles hormonaux, fibromes, endométriose ou anémie. Un suivi médical est important.",
                media: null
            }
        },
        {
            id: 20,
            type: "quiz",
            question: "Quels symptômes peuvent accompagner les règles ?",
            options: ["Crampes abdominales", "Fatigue", "Ballonnements", "Sautes d’humeur", "Maux de tête", "Aucun"],
            correct: 0,
            time: 20,
            points: 1000,
            category: "Santé",
            confirmation: {
                title: "Merci pour votre réponse !",
                text: "De nombreuses personnes ressentent ces symptômes."
            },
            explanation: {
                title: "💡 Info éducative",
                text: "Connaître ses symptômes permet de mieux anticiper et gérer son cycle.",
                media: null
            }
        }
    ]
};

// Couleurs pour les types de questions
const questionColors = {
    quiz: '#981a2c',
    'true-false': '#d48e9a',
    puzzle: '#c56b77'
};

// Couleurs pour les options de réponse
const optionColors = [
    '#981a2c',
    '#d48e9a',
    '#f4e9dd',
    '#c56b77'
];

// Export pour utilisation dans script.js
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { quizData, questionColors, optionColors };
}