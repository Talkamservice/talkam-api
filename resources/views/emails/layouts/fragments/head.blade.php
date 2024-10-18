<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Email</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet" />

    <style>
        body {
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100vw;
            height: 100vh;
        }

        * {
            font-family: "Inter", sans-serif;
            font-optical-sizing: auto;
            font-weight: 500;
            margin: 0;
        }

        .container {
            max-width: 700px;
            margin: 20px auto;
            padding: 3% 4%;
            border-radius: 5px;
            background-color: #f2f5f8;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: #333333;
        }

        .logoCont {
            text-align: left;
            padding-bottom: 3%;
            padding-top: 2%;
            color: #999999;
        }

        .logoCont>img {
            max-width: 25%;
        }

        .detailCont {
            text-align: left;
            color: #333333;
            background-color: white;
            padding: 5%;
        }

        .detailCont-p {
            margin: 5% 0 3% 0;
        }

        .link {
            color: #4a9ecf;
            font-weight: 600;
        }

        .lineCont {
            text-align: center;
            display: flex;
            justify-content: center;
        }

        .line {
            border: 1px solid #e1e1e1;
            width: 150px;
            margin-top: 8%;
            margin-bottom: 3%;
        }

        .getTalkam-h {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5%;
            text-align: center;
            color: black;
        }

        .getTalkam-d {
            text-align: center;
            padding: 0 5%;
        }

        .btnCont {
            display: flex;
            justify-content: center;
            padding: 0 0 5% 0;
            background-color: white;
            margin-top: -1%;
        }

        .horzontal {
            border: 1px solid lightgray;
            width: 30%;
            margin: 7% 0 5% 0;
            left: 35%;
            position: relative;
        }

        .apple {
            margin-right: 3%;
            display: flex;
            align-items: center;
            justify-content: end;
            border: none;
            background-color: white;
        }

        .clickBtn {
            text-decoration: none;
            border: none;
            border-radius: 6px;
            margin-bottom: 3%;
            color: white;
            padding: 2% 4%;
            background-color: #4a9ecf;
        }

        .playStore {
            display: flex;
            align-items: center;
            border: none;
            background-color: white;
        }

        .spanText {
            text-align: left;
            margin-left: 3%;
            font-size: 12px;
        }

        .appleText {
            font-size: 16px;
        }

        .spanText-1 {
            text-align: left;
            color: white;
            display: flex;
            flex-direction: column;
            margin-left: 3%;
            font-size: 12px;
        }

        .playStoreText {
            font-size: 16px;
            color: white;
            letter-spacing: 1px;
        }

        .footerlogo {
            text-align: center;
            padding-top: 10%;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .flogo {
            width: 110px;
        }

        .iconsCont {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-top: -2%;
        }

        .icon1 {
            margin-right: 30px;
            max-width: 15%;
            height: 15%;
        }

        .appleIcon,
        .playstoreIcon {
            width: 60%;
        }

        @media screen and (max-width: 768px) {
            .container {
                max-width: 400px;
            }

            .detailCont-p,
            .detailCont-ps,
            .link {
                font-size: 14px;
            }

            .getTalkam-h {
                font-size: 18px;
            }

            .getTalkam-d {
                font-size: 14px;
                padding: 0 2%;
            }

            .appleIcon,
            .playstoreIcon {
                width: 60%;
            }
   
            .appleText,
            .playStoreText {
                font-size: 15px;
            }

            .icon1 {
                margin-right: 15px;
            }

            .iconsCont {
                margin-top: -7%;
            }
        }
    </style>
</head>
